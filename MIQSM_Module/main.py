import time
import threading
from utils.logger import MIQSMLogger
from utils.metrics import MetricsCollector
from modules.task_1_1_concurrent_request_collection import ConcurrentRequestCollection
from modules.task_1_2_stochastic_queue_formation import StochasticQueueFormation
from modules.task_1_3_queue_processing_management import QueueProcessingManagement

class MIQSMModule:
    """Main Module 1: Multi-Input Queue Stochastic Modelling"""
    
    def __init__(self):
        # Initialize utilities
        self.logger = MIQSMLogger()
        self.metrics = MetricsCollector()
        
        # Initialize tasks
        self.task_1_1 = ConcurrentRequestCollection(self.logger, self.metrics)
        self.task_1_2 = StochasticQueueFormation(self.logger, self.metrics)
        self.task_1_3 = QueueProcessingManagement(self.logger, self.metrics, self.task_1_2)
        
        self.running = False
        self.processing_thread = None
        
        self.logger.info("="*60)
        self.logger.info("MIQSM MODULE INITIALIZED")
        self.logger.info("="*60)
    
    def initialize(self):
        """Initialize the module"""
        self.logger.info("Initializing queues...")
        self.task_1_2.initialize_input_queues()
        self.logger.info(f"Successfully initialized {len(self.task_1_2.queues)} queues")
    
    def start_processing(self):
        """Start background processing thread"""
        if self.running:
            self.logger.warning("Processing already running")
            return
        
        self.running = True
        self.processing_thread = threading.Thread(target=self._processing_loop, daemon=True)
        self.processing_thread.start()
        self.logger.info("Started request processing thread")
    
    def stop_processing(self):
        """Stop background processing"""
        self.running = False
        if self.processing_thread:
            self.processing_thread.join(timeout=2)
        self.logger.info("Stopped request processing thread")
    
    def _processing_loop(self):
        """Background loop for processing requests"""
        while self.running:
            # Check if there are requests to process
            total_requests = sum(q.size() for q in self.task_1_2.queues)
            
            if total_requests > 0:
                # Process one request
                self.task_1_3.process_single_request()
                
                # Monitor metrics
                self.task_1_3.generate_queue_logs()
                self.task_1_2.monitor_fairness_metrics()
                
                # Small delay to simulate processing time
                time.sleep(0.1)
            else:
                # No requests, check less frequently
                time.sleep(0.5)
    
    def collect_and_enqueue_requests(self, requests_data):
        """Collect requests and enqueue them"""
        self.logger.info(f"Collecting {len(requests_data)} requests...")
        
        # Task 1.1: Collect requests
        for req_data in requests_data:
            if req_data['type'] == 'booking':
                self.task_1_1.capture_booking_request(
                    user_id=req_data['user_id'],
                    train_id=req_data.get('train_id', 'T12345'),
                    seat_count=req_data.get('seat_count', 1),
                    priority=req_data.get('priority', 2)
                )
            elif req_data['type'] == 'cancellation':
                self.task_1_1.capture_cancellation_request(
                    user_id=req_data['user_id'],
                    booking_id=req_data.get('booking_id', 'B12345'),
                    priority=req_data.get('priority', 1)
                )
            elif req_data['type'] == 'payment':
                self.task_1_1.capture_payment_initiation_request(
                    user_id=req_data['user_id'],
                    amount=req_data.get('amount', 1000),
                    booking_id=req_data.get('booking_id', 'B12345'),
                    priority=req_data.get('priority', 3)
                )
        
        # Analyze arrival patterns
        self.task_1_1.monitor_request_arrival_rate()
        self.task_1_1.identify_burst_traffic()
        stats = self.task_1_1.log_arrival_statistics()
        
        # Get collected requests
        requests = self.task_1_1.get_all_requests()
        
        # Classify each request
        for request in requests:
            self.task_1_1.identify_request_type(request)
            self.task_1_1.assign_request_priority(request)
            self.task_1_1.tag_request_metadata(request)
        
        # Task 1.2: Enqueue requests
        self.logger.info(f"Enqueueing {len(requests)} classified requests...")
        assigned, failed = self.task_1_2.assign_requests_to_queues(requests)
        self.task_1_2.maintain_queue_states()
        
        return assigned, failed
    
    def run_validation_checks(self):
        """Run all validation and monitoring checks"""
        self.logger.info("Running validation checks...")
        
        # Task 1.3: Validation
        integrity = self.task_1_3.validate_queue_integrity()
        anomalies = self.task_1_3.detect_queue_anomalies()
        
        # Task 1.2: Fairness
        fairness = self.task_1_2.monitor_fairness_metrics()
        starvation = self.task_1_2.prevent_request_starvation()
        
        return {
            'integrity': integrity,
            'anomalies': anomalies,
            'fairness': fairness,
            'starvation': starvation
        }
    
    def get_system_status(self):
        """Get current system status"""
        queue_states = self.task_1_2.maintain_queue_states()
        metrics_summary = self.metrics.get_summary()
        
        status = {
            'queues': queue_states,
            'metrics': metrics_summary,
            'total_requests_in_queues': sum(q['current_size'] for q in queue_states),
            'total_processed': sum(q['total_served'] for q in queue_states)
        }
        
        return status
    
    def shutdown(self):
        """Gracefully shutdown the module"""
        self.logger.info("Shutting down MIQSM Module...")
        self.stop_processing()
        
        # Final statistics
        final_status = self.get_system_status()
        self.logger.info(f"Final Status: {final_status}")
        
        self.logger.info("="*60)
        self.logger.info("MIQSM MODULE SHUTDOWN COMPLETE")
        self.logger.info("="*60)