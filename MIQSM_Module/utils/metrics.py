import time
from collections import defaultdict
import threading

class MetricsCollector:
    """Collects and manages system metrics"""
    
    def __init__(self):
        self.lock = threading.Lock()
        self.arrival_rates = []
        self.burst_events = []
        self.queue_lengths = defaultdict(list)
        self.waiting_times = []
        self.fairness_scores = []
        self.anomalies = []
        self.start_time = time.time()
    
    def record_arrival_rate(self, rate, timestamp=None):
        """Record request arrival rate"""
        with self.lock:
            self.arrival_rates.append({
                'rate': rate,
                'timestamp': timestamp or time.time()
            })
    
    def record_burst_event(self, num_requests, timestamp=None):
        """Record burst traffic event"""
        with self.lock:
            self.burst_events.append({
                'count': num_requests,
                'timestamp': timestamp or time.time()
            })
    
    def record_queue_length(self, queue_id, length):
        """Record queue length"""
        with self.lock:
            self.queue_lengths[queue_id].append({
                'length': length,
                'timestamp': time.time()
            })
    
    def record_waiting_time(self, waiting_time):
        """Record request waiting time"""
        with self.lock:
            self.waiting_times.append(waiting_time)
    
    def record_fairness_score(self, score):
        """Record fairness metric"""
        with self.lock:
            self.fairness_scores.append({
                'score': score,
                'timestamp': time.time()
            })
    
    def record_anomaly(self, anomaly_type, description):
        """Record detected anomaly"""
        with self.lock:
            self.anomalies.append({
                'type': anomaly_type,
                'description': description,
                'timestamp': time.time()
            })
    
    def get_summary(self):
        """Get metrics summary"""
        with self.lock:
            return {
                'total_arrivals': len(self.arrival_rates),
                'burst_events': len(self.burst_events),
                'avg_waiting_time': sum(self.waiting_times) / len(self.waiting_times) if self.waiting_times else 0,
                'anomalies_detected': len(self.anomalies),
                'runtime': time.time() - self.start_time
            }