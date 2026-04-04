from flask import Flask
from flask_socketio import SocketIO, emit
import threading
import time
import json
from main import MIQSMModule
import random

app = Flask(__name__)
app.config['SECRET_KEY'] = 'miqsm_secret_key'
socketio = SocketIO(app, cors_allowed_origins="*", async_mode='threading')

# Global module instance
miqsm_module = None
processing_active = False

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

def emit_status_update():
    """Emit current system status to all connected clients"""
    global miqsm_module
    
    if miqsm_module:
        status = miqsm_module.get_system_status()
        
        # Format queue data for charts
        queue_data = []
        for q in status['queues']:
            queue_data.append({
                'queue_id': q['queue_id'],
                'current_size': q['current_size'],
                'total_served': q['total_served'],
                'avg_waiting_time': round(q['avg_waiting_time'], 2)
            })
        
        # USE socketio.emit with broadcast=True instead of just emit
        socketio.emit('status_update', {
            'total_in_queues': status['total_requests_in_queues'],
            'total_processed': status['total_processed'],
            'queues': queue_data,
            'timestamp': time.time()
        }, broadcast=True)

def background_processing():
    """Background thread for processing requests"""
    global miqsm_module, processing_active
    
    while processing_active:
        if miqsm_module:
            # Process requests
            total_requests = sum(q.size() for q in miqsm_module.task_1_2.queues)
            
            if total_requests > 0:
                # Process one request
                request = miqsm_module.task_1_3.process_single_request()
                
                if request:
                    # Emit request processed event with broadcast
                    socketio.emit('request_processed', {
                        'request_id': request.request_id[:8],
                        'type': request.request_type,
                        'priority': request.priority,
                        'waiting_time': round(request.get_waiting_time(), 2)
                    }, broadcast=True)
                
                # Emit status update
                emit_status_update()
                
                time.sleep(0.1)
            else:
                time.sleep(0.5)
        else:
            time.sleep(1)

@app.route('/')
def index():
    """Serve the main dashboard"""
    return """<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MIQSM - Railway Reservation System Dashboard</title>
    <script src="https://cdn.socket.io/4.5.4/socket.io.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            text-align: center;
        }

        .header h1 {
            color: #667eea;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .controls {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 3rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: #666;
            font-size: 1rem;
            margin-top: 10px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .chart-card h3 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }

        .activity-feed {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-feed h3 {
            color: #667eea;
            margin-bottom: 20px;
        }

        .activity-item {
            padding: 12px;
            border-left: 4px solid #667eea;
            background: #f8f9ff;
            margin-bottom: 10px;
            border-radius: 5px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .activity-time {
            color: #999;
            font-size: 0.85rem;
        }

        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-active {
            background: #38ef7d;
            box-shadow: 0 0 10px #38ef7d;
        }

        .status-inactive {
            background: #f5576c;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #666;
            font-weight: 600;
        }

        .input-group input,
        .input-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-high {
            background: #fee140;
            color: #fa709a;
        }

        .badge-medium {
            background: #38ef7d;
            color: #11998e;
        }

        .badge-low {
            background: #f5576c;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚂 MIQSM Dashboard</h1>
            <p>Multi-Input Queue Stochastic Modelling - Railway Reservation System</p>
            <div style="margin-top: 15px;">
                <span class="status-indicator" id="systemStatus"></span>
                <span id="systemStatusText">Not Connected</span>
            </div>
        </div>

        <div class="controls">
            <button class="btn btn-primary" onclick="initializeSystem()" id="initBtn">
                🔧 Initialize System
            </button>
            <button class="btn btn-success" onclick="startProcessing()" id="startBtn" disabled>
                ▶️ Start Processing
            </button>
            <button class="btn btn-warning" onclick="stopProcessing()" id="stopBtn" disabled>
                ⏸️ Stop Processing
            </button>
            <button class="btn btn-primary" onclick="showAddRequestsModal()" id="addBtn" disabled>
                ➕ Add Requests
            </button>
            <button class="btn btn-danger" onclick="runValidation()" id="validateBtn" disabled>
                🔍 Run Validation
            </button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="totalInQueues">0</div>
                <div class="stat-label">Requests in Queues</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="totalProcessed">0</div>
                <div class="stat-label">Total Processed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="anomalyCount">0</div>
                <div class="stat-label">Anomalies Detected</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="processingRate">0</div>
                <div class="stat-label">Requests/Second</div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3>📊 Queue Distribution</h3>
                <canvas id="queueChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>⏱️ Average Waiting Time</h3>
                <canvas id="waitingTimeChart"></canvas>
            </div>
        </div>

        <div class="activity-feed">
            <h3>📝 Recent Activity</h3>
            <div id="activityFeed"></div>
        </div>
    </div>

    <div class="modal" id="addRequestsModal">
        <div class="modal-content">
            <h2 style="color: #667eea; margin-bottom: 20px;">Add Requests</h2>
            <div class="input-group">
                <label>Number of Requests</label>
                <input type="number" id="requestCount" value="50" min="1" max="500">
            </div>
            <div class="input-group">
                <label>Scenario Type</label>
                <select id="scenarioType">
                    <option value="normal">Normal Load</option>
                    <option value="tatkal">Tatkal (High Priority)</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn btn-primary" onclick="addRequests()">Add</button>
                <button class="btn btn-warning" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const socket = io();

        // ADD THIS DEBUGGING CODE HERE:
        socket.on('connect', () => {
            console.log('✅ Socket connected! ID:', socket.id);
        });

        socket.on('connect_error', (error) => {
            console.error('❌ Connection error:', error);
        });

        socket.on('disconnect', (reason) => {
            console.log('🔌 Disconnected:', reason);
        });

        // Listen to ALL events for debugging
        socket.onAny((eventName, ...args) => {
            console.log('📨 Event received:', eventName, args);
        });
        let queueChart, waitingTimeChart;
        let processedCount = 0;
        let lastProcessedTime = Date.now();

        socket.on('connect', () => {
            console.log('Connected to server!');
            document.getElementById('systemStatus').className = 'status-indicator status-active pulse';
            document.getElementById('systemStatusText').textContent = 'Connected';
            addActivity('System', 'Connected to server');
        });

        socket.on('system_initialized', (data) => {
            console.log('System initialized:', data);
            addActivity('System', data.message);
            document.getElementById('startBtn').disabled = false;
            document.getElementById('addBtn').disabled = false;
            document.getElementById('validateBtn').disabled = false;
            document.getElementById('initBtn').disabled = true;
        });

        socket.on('processing_started', (data) => {
            console.log('Processing started:', data);
            addActivity('Processing', 'Background processing started');
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
        });

        socket.on('processing_stopped', (data) => {
            console.log('Processing stopped:', data);
            addActivity('Processing', 'Background processing stopped');
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
        });

        socket.on('status_update', (data) => {
            console.log('Status update:', data);
            updateStats(data);
            updateCharts(data);
        });

        socket.on('request_processed', (data) => {
            console.log('Request processed:', data);
            processedCount++;
            const priorityBadge = data.priority === 3 ? 'high' : data.priority === 2 ? 'medium' : 'low';
            addActivity(
                data.type,
                `Request ${data.request_id} processed <span class="badge badge-${priorityBadge}">P${data.priority}</span> (waited ${data.waiting_time}s)`
            );
        });

        socket.on('requests_added', (data) => {
            console.log('Requests added:', data);
            addActivity('Requests', `${data.assigned} requests added (${data.failed} failed)`);
        });

        socket.on('validation_results', (data) => {
            console.log('Validation results:', data);
            document.getElementById('anomalyCount').textContent = data.anomalies;
            addActivity('Validation', `Found ${data.anomalies} anomalies, ${data.starvation} starvation cases`);
        });

        function initializeCharts() {
            const queueCtx = document.getElementById('queueChart').getContext('2d');
            queueChart = new Chart(queueCtx, {
                type: 'bar',
                data: {
                    labels: ['Queue 0', 'Queue 1', 'Queue 2', 'Queue 3', 'Queue 4'],
                    datasets: [{
                        label: 'Current Size',
                        data: [0, 0, 0, 0, 0],
                        backgroundColor: 'rgba(102, 126, 234, 0.7)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            const waitingCtx = document.getElementById('waitingTimeChart').getContext('2d');
            waitingTimeChart = new Chart(waitingCtx, {
                type: 'line',
                data: {
                    labels: ['Queue 0', 'Queue 1', 'Queue 2', 'Queue 3', 'Queue 4'],
                    datasets: [{
                        label: 'Avg Waiting Time (s)',
                        data: [0, 0, 0, 0, 0],
                        backgroundColor: 'rgba(56, 239, 125, 0.2)',
                        borderColor: 'rgba(56, 239, 125, 1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function updateStats(data) {
            console.log('Updating stats:', data);
            document.getElementById('totalInQueues').textContent = data.total_in_queues;
            document.getElementById('totalProcessed').textContent = data.total_processed;
            
            const now = Date.now();
            const timeDiff = (now - lastProcessedTime) / 1000;
            if (timeDiff > 1) {
                const rate = (processedCount / timeDiff).toFixed(1);
                document.getElementById('processingRate').textContent = rate;
                processedCount = 0;
                lastProcessedTime = now;
            }
        }

        function updateCharts(data) {
            const queueSizes = data.queues.map(q => q.current_size);
            const waitingTimes = data.queues.map(q => q.avg_waiting_time);

            queueChart.data.datasets[0].data = queueSizes;
            queueChart.update();

            waitingTimeChart.data.datasets[0].data = waitingTimes;
            waitingTimeChart.update();
        }

        function addActivity(type, message) {
            const feed = document.getElementById('activityFeed');
            const item = document.createElement('div');
            item.className = 'activity-item';
            const time = new Date().toLocaleTimeString();
            item.innerHTML = `
                <strong>${type}:</strong> ${message}
                <div class="activity-time">${time}</div>
            `;
            feed.insertBefore(item, feed.firstChild);
            
            while (feed.children.length > 50) {
                feed.removeChild(feed.lastChild);
            }
        }

        function initializeSystem() {
            console.log('Initializing system...');
            socket.emit('initialize_system');
        }

        function startProcessing() {
            console.log('Starting processing...');
            socket.emit('start_processing');
        }

        function stopProcessing() {
            console.log('Stopping processing...');
            socket.emit('stop_processing');
        }

        function showAddRequestsModal() {
            document.getElementById('addRequestsModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('addRequestsModal').style.display = 'none';
        }

        function addRequests() {
            const count = parseInt(document.getElementById('requestCount').value);
            const scenario = document.getElementById('scenarioType').value;
            
            console.log('Adding requests:', count, scenario);
            socket.emit('add_requests', { count, scenario });
            closeModal();
        }

        function runValidation() {
            console.log('Running validation...');
            socket.emit('run_validation');
        }

        window.onload = () => {
            initializeCharts();
        };
    </script>
</body>
</html>"""

@socketio.on('connect')
def handle_connect():
    print("✅ Client connected!")
    emit('connection_response', {'status': 'connected'})

@socketio.on('disconnect')
def handle_disconnect():
    print("❌ Client disconnected!")

@socketio.on('initialize_system')
def handle_initialize():
    global miqsm_module
    
    print("🔧 Initializing system...")
    miqsm_module = MIQSMModule()
    miqsm_module.initialize()
    
    # Use broadcast=True to send to all clients
    socketio.emit('system_initialized', {'message': 'System initialized with 5 queues'}, broadcast=True)
    emit_status_update()

@socketio.on('start_processing')
def handle_start_processing():
    global processing_active
    
    print("▶️ Starting processing...")
    if not processing_active:
        processing_active = True
        thread = threading.Thread(target=background_processing, daemon=True)
        thread.start()
        socketio.emit('processing_started', {'message': 'Processing started'}, broadcast=True)

@socketio.on('stop_processing')
def handle_stop_processing():
    global processing_active
    
    print("⏸️ Stopping processing...")
    processing_active = False
    socketio.emit('processing_stopped', {'message': 'Processing stopped'}, broadcast=True)

@socketio.on('add_requests')
def handle_add_requests(data):
    global miqsm_module
    
    print(f"➕ Adding requests: {data}")
    count = data.get('count', 50)
    scenario = data.get('scenario', 'normal')
    
    requests = generate_sample_requests(count)
    
    if scenario == 'tatkal':
        for req in requests[:int(count * 0.7)]:
            if req['type'] == 'booking':
                req['priority'] = 3
    
    assigned, failed = miqsm_module.collect_and_enqueue_requests(requests)
    
    print(f"✓ Requests added: {assigned} assigned, {failed} failed")
    
    socketio.emit('requests_added', {
        'assigned': assigned,
        'failed': failed,
        'total': count
    }, broadcast=True)
    
    emit_status_update()

@socketio.on('run_validation')
def handle_validation():
    global miqsm_module
    
    print("🔍 Running validation...")
    if miqsm_module:
        validation = miqsm_module.run_validation_checks()
        
        socketio.emit('validation_results', {
            'anomalies': len(validation['anomalies']),
            'starvation': len(validation['starvation']),
            'anomaly_details': validation['anomalies'][:5] if validation['anomalies'] else [],
            'fairness_score': validation.get('fairness', {}).get('balance_score', 0)
        }, broadcast=True)

if __name__ == '__main__':
    print("="*60)
    print("🚂 MIQSM Web Dashboard Starting...")
    print("="*60)
    print("📡 Open your browser and go to: http://localhost:5000")
    print("="*60)
    socketio.run(app, debug=True, host='0.0.0.0', port=5000, allow_unsafe_werkzeug=True)