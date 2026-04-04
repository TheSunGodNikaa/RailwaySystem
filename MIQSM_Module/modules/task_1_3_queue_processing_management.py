import time
from config.constants import *
from utils.logger import MIQSMLogger
from utils.metrics import MetricsCollector

class QueueProcessingManagement:
    """Task 1.3: Queue Processing Management"""
    
    def __init__(self, logger, metrics, queue_formation):
        self.logger = logger
        self.metrics = metrics
        self.queue_formation = queue_formation
        self.processed_requests = []
        self.anomalies_detected = []
    
    # ========== ACTIVITY 1.3.1: Request Dequeueing ==========
    
    def remove_request_from_queue(self, queue_index):
        """Program 1.3.1.1: Remove request from queue"""
        if queue_index < 0 or queue_index >= len(self.queue_formation.queues):
            self.logger.error(f"Invalid queue index: {queue_index}")
            return None
        
        request = self.queue_formation.queues[queue_index].dequeue()
        
        if request:
            self.logger.info(f"Removed request {request.request_id} from Queue {queue_index}")
        else:
            self.logger.debug(f"Queue {queue_index} is empty")
        
        return request
    
    def forward_to_transaction_layer(self, request):
        """Program 1.3.1.2: Forward to transaction layer"""
        if not request:
            return False
        
        # Simulate forwarding to next layer (Module 2: Two-Phase Locking)
        request.status = "FORWARDED_TO_2PL"
        request.metadata['forwarded_at'] = time.time()
        request.metadata['target_module'] = "Two-Phase Locking Protocol"
        
        self.logger.info(f"Forwarded request {request.request_id} to Transaction Layer (2PL)")
        return True
    
    def update_queue_status(self, queue_index):
        """Program 1.3.1.3: Update queue status"""
        if queue_index < 0 or queue_index >= len(self.queue_formation.queues):
            return None
        
        queue = self.queue_formation.queues[queue_index]
        status = queue.get_stats()
        
        status['last_updated'] = time.time()
        status['utilization'] = status['current_size'] / queue.max_size
        
        self.logger.debug(f"Queue {queue_index} status updated: {status}")
        return status
    
    # ========== ACTIVITY 1.3.2: Queue Monitoring ==========
    
    def track_queue_length(self):
        """Program 1.3.2.1: Track queue length"""
        queue_lengths = {}
        
        for queue in self.queue_formation.queues:
            length = queue.size()
            queue_lengths[queue.queue_id] = length
            self.metrics.record_queue_length(queue.queue_id, length)
        
        self.logger.debug(f"Queue lengths tracked: {queue_lengths}")
        return queue_lengths
    
    def monitor_waiting_time(self):
        """Program 1.3.2.2: Monitor waiting time"""
        waiting_times = {}
        
        for queue in self.queue_formation.queues:
            if not queue.is_empty():
                oldest_request = queue.peek()
                waiting_time = oldest_request.get_waiting_time()
                waiting_times[queue.queue_id] = waiting_time
                self.metrics.record_waiting_time(waiting_time)
                
                if waiting_time > MAX_WAITING_TIME:
                    self.logger.warning(f"Queue {queue.queue_id}: High waiting time {waiting_time:.2f}s")
        
        return waiting_times
    
    def generate_queue_logs(self):
        """Program 1.3.2.3: Generate queue logs"""
        log_data = {
            'timestamp': time.time(),
            'queue_lengths': self.track_queue_length(),
            'waiting_times': self.monitor_waiting_time(),
            'queue_stats': []
        }
        
        for queue in self.queue_formation.queues:
            stats = queue.get_stats()
            log_data['queue_stats'].append(stats)
        
        self.logger.info(f"Queue logs generated: {log_data}")
        return log_data
    
    # ========== ACTIVITY 1.3.3: Queue Validation ==========
    
    def validate_queue_integrity(self):
        """Program 1.3.3.1: Validate queue integrity"""
        validation_results = []
        
        for queue in self.queue_formation.queues:
            is_valid = True
            issues = []
            
            # Check 1: Queue size within bounds
            if queue.size() > queue.max_size:
                is_valid = False
                issues.append(f"Size overflow: {queue.size()} > {queue.max_size}")
            
            # Check 2: Statistics consistency
            if queue.total_served > queue.total_added:
                is_valid = False
                issues.append(f"Served ({queue.total_served}) > Added ({queue.total_added})")
            
            # Check 3: Queue not null
            if queue.queue is None:
                is_valid = False
                issues.append("Queue object is None")
            
            validation_results.append({
                'queue_id': queue.queue_id,
                'is_valid': is_valid,
                'issues': issues
            })
            
            if not is_valid:
                self.logger.error(f"Queue {queue.queue_id} validation failed: {issues}")
            else:
                self.logger.debug(f"Queue {queue.queue_id} validation passed")
        
        return validation_results
    
    def detect_queue_anomalies(self):
        """Program 1.3.3.2: Detect queue anomalies"""
        anomalies = []
        
        for queue in self.queue_formation.queues:
            # Anomaly 1: Queue constantly full
            if queue.size() >= queue.max_size * 0.95:
                anomaly = {
                    'type': 'QUEUE_SATURATION',
                    'queue_id': queue.queue_id,
                    'severity': 'HIGH',
                    'message': f"Queue {queue.queue_id} is {(queue.size()/queue.max_size)*100:.1f}% full"
                }
                anomalies.append(anomaly)
                self.metrics.record_anomaly('QUEUE_SATURATION', anomaly['message'])
            
            # Anomaly 2: Unexpectedly empty during high load
            avg_size = sum(q.size() for q in self.queue_formation.queues) / len(self.queue_formation.queues)
            if queue.size() == 0 and avg_size > 50:
                anomaly = {
                    'type': 'QUEUE_UNDERUTILIZATION',
                    'queue_id': queue.queue_id,
                    'severity': 'MEDIUM',
                    'message': f"Queue {queue.queue_id} empty while others are loaded"
                }
                anomalies.append(anomaly)
                self.metrics.record_anomaly('QUEUE_UNDERUTILIZATION', anomaly['message'])
            
            # Anomaly 3: Excessive waiting time
            if not queue.is_empty():
                oldest = queue.peek()
                if oldest.get_waiting_time() > MAX_WAITING_TIME * 2:
                    anomaly = {
                        'type': 'EXCESSIVE_WAITING',
                        'queue_id': queue.queue_id,
                        'severity': 'CRITICAL',
                        'message': f"Request waiting {oldest.get_waiting_time():.2f}s in Queue {queue.queue_id}"
                    }
                    anomalies.append(anomaly)
                    self.metrics.record_anomaly('EXCESSIVE_WAITING', anomaly['message'])
        
        self.anomalies_detected.extend(anomalies)
        
        if anomalies:
            self.logger.warning(f"Detected {len(anomalies)} anomalies: {anomalies}")
        
        return anomalies
    
    def approve_request_flow(self, request):
        """Program 1.3.3.3: Approve request flow"""
        if not request:
            return False
        
        # Validation checks before approval
        checks = {
            'has_valid_id': bool(request.request_id),
            'has_valid_type': request.request_type in [REQUEST_TYPE_BOOKING, REQUEST_TYPE_CANCELLATION, REQUEST_TYPE_PAYMENT],
            'has_user_id': bool(request.user_id),
            'within_time_limit': request.get_waiting_time() < MAX_WAITING_TIME * 3,
            'has_metadata': bool(request.metadata)
        }
        
        all_passed = all(checks.values())
        
        if all_passed:
            request.status = "APPROVED"
            self.processed_requests.append(request)
            self.logger.info(f"Request {request.request_id} APPROVED for processing")
        else:
            request.status = "REJECTED"
            failed_checks = [k for k, v in checks.items() if not v]
            self.logger.error(f"Request {request.request_id} REJECTED. Failed checks: {failed_checks}")
        
        return all_passed
    
    def process_single_request(self):
        """Process one request through the complete flow"""
        # Select next request
        request, queue_idx = self.queue_formation.select_next_request()
        
        if not request:
            self.logger.debug("No requests available to process")
            return None
        
        # Dequeue
        request = self.remove_request_from_queue(queue_idx)
        
        # Validate
        if not self.approve_request_flow(request):
            return None
        
        # Forward
        self.forward_to_transaction_layer(request)
        
        # Update status
        self.update_queue_status(queue_idx)
        
        return request