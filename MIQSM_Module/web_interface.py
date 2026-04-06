from flask import Flask, render_template
from flask_socketio import SocketIO, emit
import time
import json
import os
from main import MIQSMModule
import random
from config.constants import MAX_WAITING_TIME

app = Flask(__name__)
app.config['SECRET_KEY'] = 'miqsm_secret_key'
socketio = SocketIO(app, cors_allowed_origins="*", async_mode="threading")

# Global module instance
miqsm_module = None
processing_active = False
processing_thread = None
last_rate_timestamp = None
last_processed_total = 0
external_event_offset = 0
last_external_ingest_timestamp = None

EVENT_FILE_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'runtime', 'app_events.jsonl')

def generate_sample_requests(count=50):
    """Generate sample requests"""
    requests = []
    request_types = ['booking', 'cancellation', 'payment']
    
    for i in range(count):
        req_type = random.choice(request_types)
        
        if req_type == 'booking':
            request = {
                'type': 'booking',
                'user_id': f'USER_{random.randint(1000, 9999)}',
                'train_id': f'T{random.randint(10000, 99999)}',
                'seat_count': random.randint(1, 4),
                'priority': random.choice([1, 2, 3])
            }
        elif req_type == 'cancellation':
            request = {
                'type': 'cancellation',
                'user_id': f'USER_{random.randint(1000, 9999)}',
                'booking_id': f'B{random.randint(10000, 99999)}',
                'priority': 1
            }
        else:
            request = {
                'type': 'payment',
                'user_id': f'USER_{random.randint(1000, 9999)}',
                'amount': random.randint(500, 5000),
                'booking_id': f'B{random.randint(10000, 99999)}',
                'priority': 3
            }
        
        requests.append(request)
    
    return requests

def initialize_external_event_cursor(skip_existing=False):
    """Position the external-event cursor."""
    global external_event_offset

    directory = os.path.dirname(EVENT_FILE_PATH)
    os.makedirs(directory, exist_ok=True)

    if not os.path.exists(EVENT_FILE_PATH):
        with open(EVENT_FILE_PATH, 'a', encoding='utf-8'):
            pass

    external_event_offset = os.path.getsize(EVENT_FILE_PATH) if skip_existing else 0

def build_requests_from_event(event_record):
    """Translate PHP-side system events into MIQSM-compatible requests."""
    event_name = event_record.get('event_name')
    payload = event_record.get('payload') or {}
    user_ref = str(payload.get('user_id') or payload.get('username') or payload.get('transaction_id') or 'SYSTEM')

    if event_name == 'booking_confirmed':
        return [{
            'type': 'booking',
            'user_id': user_ref,
            'train_id': str(payload.get('train_id') or 'LIVE'),
            'seat_count': max(int(payload.get('seat_count') or 1), 1),
            'priority': 3
        }]

    if event_name in {'cancellation_requested', 'booking_cancelled'}:
        return [{
            'type': 'cancellation',
            'user_id': user_ref,
            'booking_id': str(payload.get('transaction_id') or payload.get('booking_id') or 'LIVE'),
            'priority': 1
        }]

    if event_name in {'passenger_login', 'clerk_login', 'clerk_logout'}:
        return [{
            'type': 'payment',
            'user_id': user_ref,
            'amount': 0,
            'booking_id': str(payload.get('transaction_id') or event_name.upper()),
            'priority': 2
        }]

    return []

def drain_external_events():
    """Read new PHP-side events and enqueue them into MIQSM."""
    global external_event_offset, miqsm_module, last_external_ingest_timestamp

    if miqsm_module is None or not os.path.exists(EVENT_FILE_PATH):
        return 0

    collected_requests = []

    with open(EVENT_FILE_PATH, 'r', encoding='utf-8') as event_file:
        event_file.seek(external_event_offset)
        for line in event_file:
            line = line.strip()
            if not line:
                continue

            try:
                event_record = json.loads(line)
            except json.JSONDecodeError:
                continue

            collected_requests.extend(build_requests_from_event(event_record))

        external_event_offset = event_file.tell()

    if collected_requests:
        miqsm_module.collect_and_enqueue_requests(collected_requests)
        last_external_ingest_timestamp = time.time()
        socketio.emit('external_requests_added', {
            'total': len(collected_requests)
        })
        emit_status_update()

    return len(collected_requests)

def emit_status_update():
    """Emit current system status to all connected clients"""
    global miqsm_module, last_rate_timestamp, last_processed_total
    
    if miqsm_module:
        status = miqsm_module.get_system_status()
        now = time.time()

        # Calculate requests/sec based on processed-count delta.
        if last_rate_timestamp is None:
            requests_per_second = 0.0
            last_rate_timestamp = now
            last_processed_total = status['total_processed']
        else:
            elapsed = max(now - last_rate_timestamp, 1e-6)
            delta_processed = max(status['total_processed'] - last_processed_total, 0)
            requests_per_second = delta_processed / elapsed
            last_rate_timestamp = now
            last_processed_total = status['total_processed']

        # Compute live anomaly count without mutating internal anomaly history.
        live_anomalies = 0
        queues = miqsm_module.task_1_2.queues
        avg_queue_size = (sum(q.size() for q in queues) / len(queues)) if queues else 0.0
        
        # Format queue data for charts
        queue_data = []
        for idx, q in enumerate(status['queues']):
            queue_obj = queues[idx]
            queue_size = q['current_size']

            # More responsive waiting metric for UI chart:
            # use oldest-in-queue waiting when queue is active, otherwise fallback to served average.
            if queue_obj.is_empty():
                live_waiting = float(q['avg_waiting_time'])
            else:
                live_waiting = float(queue_obj.peek().get_waiting_time())

            # Live anomaly checks (mirrors validation intent, no side effects).
            if queue_size >= queue_obj.max_size * 0.95:
                live_anomalies += 1
            if queue_size == 0 and avg_queue_size > 50:
                live_anomalies += 1
            if not queue_obj.is_empty() and queue_obj.peek().get_waiting_time() > MAX_WAITING_TIME * 2:
                live_anomalies += 1

            queue_data.append({
                'queue_id': q['queue_id'],
                'current_size': queue_size,
                'total_served': q['total_served'],
                'avg_waiting_time': round(live_waiting, 2)
            })
        
        socketio.emit('status_update', {
            'total_in_queues': status['total_requests_in_queues'],
            'total_processed': status['total_processed'],
            'anomalies_detected': live_anomalies,
            'requests_per_second': round(requests_per_second, 2),
            'queues': queue_data,
            'timestamp': now
        })

def background_processing():
    """Background thread for processing requests"""
    global miqsm_module, processing_active
    
    while processing_active:
        if miqsm_module:
            drain_external_events()

            # Process requests
            total_requests = sum(q.size() for q in miqsm_module.task_1_2.queues)
            
            if total_requests > 0:
                # Hold freshly ingested external requests briefly so they are visible in the queue graph.
                if last_external_ingest_timestamp and (time.time() - last_external_ingest_timestamp) < 1.5:
                    emit_status_update()
                    socketio.sleep(0.2)
                    continue

                # Process one request
                request = miqsm_module.task_1_3.process_single_request()
                
                if request:
                    # Emit request processed event
                    socketio.emit('request_processed', {
                        'request_id': request.request_id[:8],
                        'type': request.request_type,
                        'priority': request.priority,
                        'waiting_time': round(request.get_waiting_time(), 2)
                    })
                
                # Emit status update
                emit_status_update()
                
                socketio.sleep(0.6)
            else:
                # Still emit periodic updates so charts remain in sync.
                emit_status_update()
                socketio.sleep(0.5)
        else:
            socketio.sleep(1)


def ensure_processing_started():
    """Start processing exactly once."""
    global processing_active, processing_thread
    if processing_active:
        return False

    processing_active = True
    processing_thread = socketio.start_background_task(background_processing)
    return True

@app.route('/')
def index():
    """Serve the main dashboard"""
    return render_template('dashboard.html')

@socketio.on('connect')
def handle_connect():
    """Handle client connection"""
    emit('connection_response', {'status': 'connected'})
    if miqsm_module is None:
        handle_initialize()
    else:
        emit_status_update()

@socketio.on('initialize_system')
def handle_initialize():
    """Initialize the MIQSM module"""
    global miqsm_module, processing_active, last_rate_timestamp, last_processed_total
    
    processing_active = False
    miqsm_module = MIQSMModule()
    miqsm_module.initialize()
    initialize_external_event_cursor(skip_existing=False)
    last_rate_timestamp = None
    last_processed_total = 0
    drain_external_events()
    
    emit('system_initialized', {'message': 'System initialized with 5 queues'})
    if ensure_processing_started():
        emit('processing_started', {'message': 'Processing started automatically'})
    emit_status_update()

@socketio.on('start_processing')
def handle_start_processing():
    """Start background processing"""
    if miqsm_module is None:
        emit('error', {'message': 'Initialize the system first'})
        return

    if ensure_processing_started():
        emit('processing_started', {'message': 'Processing started'})

@socketio.on('stop_processing')
def handle_stop_processing():
    """Stop background processing"""
    global processing_active
    processing_active = False
    emit('processing_stopped', {'message': 'Processing stopped'})

@socketio.on('add_requests')
def handle_add_requests(data):
    """Add requests to the system"""
    global miqsm_module

    if miqsm_module is None:
        emit('error', {'message': 'Initialize the system first'})
        return
    
    count = data.get('count', 50)
    scenario = data.get('scenario', 'normal')
    
    requests = generate_sample_requests(count)
    
    # Adjust priorities based on scenario
    if scenario == 'tatkal':
        for req in requests[:int(count * 0.7)]:
            if req['type'] == 'booking':
                req['priority'] = 3
    
    assigned, failed = miqsm_module.collect_and_enqueue_requests(requests)
    
    emit('requests_added', {
        'assigned': assigned,
        'failed': failed,
        'total': count
    })

    # Auto-start processing once requests are added so UI updates continue.
    if ensure_processing_started():
        emit('processing_started', {'message': 'Processing started automatically'})
    
    emit_status_update()

@socketio.on('run_validation')
def handle_validation():
    """Run system validation"""
    global miqsm_module
    
    if miqsm_module:
        validation = miqsm_module.run_validation_checks()
        
        emit('validation_results', {
            'anomalies': len(validation['anomalies']),
            'starvation': len(validation['starvation']),
            'anomaly_details': validation['anomalies'][:5],
            'fairness_score': validation['fairness'].get('balance_score', 0)
        })
    else:
        emit('error', {'message': 'Initialize the system first'})

@socketio.on('get_metrics')
def handle_get_metrics():
    """Get detailed metrics"""
    global miqsm_module
    
    if miqsm_module:
        metrics = miqsm_module.metrics.get_summary()
        emit('metrics_data', metrics)
    else:
        emit('error', {'message': 'Initialize the system first'})

if __name__ == '__main__':
    socketio.run(app, debug=True, host='0.0.0.0', port=5000, allow_unsafe_werkzeug=True)
