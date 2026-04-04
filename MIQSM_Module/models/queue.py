from collections import deque
import threading

class InputQueue:
    """Thread-safe queue for handling concurrent requests"""
    
    def __init__(self, queue_id, max_size=1000):
        self.queue_id = queue_id
        self.max_size = max_size
        self.queue = deque()
        self.lock = threading.Lock()
        self.total_served = 0
        self.total_added = 0
        self.total_waiting_time = 0.0
    
    def enqueue(self, request):
        """Add request to queue"""
        with self.lock:
            if len(self.queue) >= self.max_size:
                return False
            self.queue.append(request)
            request.queue_assignment = self.queue_id
            self.total_added += 1
            return True
    
    def dequeue(self):
        """Remove and return next request"""
        with self.lock:
            if not self.queue:
                return None
            request = self.queue.popleft()
            self.total_served += 1
            self.total_waiting_time += request.get_waiting_time()
            return request
    
    def peek(self):
        """View next request without removing"""
        with self.lock:
            return self.queue[0] if self.queue else None
    
    def size(self):
        """Get current queue size"""
        with self.lock:
            return len(self.queue)
    
    def is_empty(self):
        """Check if queue is empty"""
        with self.lock:
            return len(self.queue) == 0
    
    def get_average_waiting_time(self):
        """Calculate average waiting time"""
        with self.lock:
            if self.total_served == 0:
                return 0.0
            return self.total_waiting_time / self.total_served
    
    def get_stats(self):
        """Get queue statistics"""
        with self.lock:
            avg_waiting_time = self.total_waiting_time / self.total_served if self.total_served > 0 else 0.0
            return {
                'queue_id': self.queue_id,
                'current_size': len(self.queue),
                'total_added': self.total_added,
                'total_served': self.total_served,
                'avg_waiting_time': avg_waiting_time
            }
