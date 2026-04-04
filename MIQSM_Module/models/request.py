import time
import uuid
from datetime import datetime

class BookingRequest:
    """Represents a single booking/cancellation/payment request"""
    
    def __init__(self, request_type, user_id, train_id=None, seat_count=1, 
                 priority=2, metadata=None):
        self.request_id = str(uuid.uuid4())
        self.request_type = request_type
        self.user_id = user_id
        self.train_id = train_id
        self.seat_count = seat_count
        self.priority = priority
        self.metadata = metadata or {}
        self.timestamp = time.time()
        self.arrival_time = datetime.now()
        self.status = "PENDING"
        self.queue_assignment = None
        self.processing_start_time = None
        self.processing_end_time = None
    
    def get_waiting_time(self):
        """Calculate how long request has been waiting"""
        if self.processing_start_time:
            return self.processing_start_time - self.timestamp
        return time.time() - self.timestamp
    
    def start_processing(self):
        """Mark request as being processed"""
        self.processing_start_time = time.time()
        self.status = "PROCESSING"
    
    def complete_processing(self, success=True):
        """Mark request as completed"""
        self.processing_end_time = time.time()
        self.status = "COMPLETED" if success else "FAILED"
    
    def to_dict(self):
        """Convert to dictionary for logging"""
        return {
            'request_id': self.request_id,
            'request_type': self.request_type,
            'user_id': self.user_id,
            'priority': self.priority,
            'status': self.status,
            'timestamp': self.timestamp,
            'waiting_time': self.get_waiting_time()
        }
    
    def __repr__(self):
        return f"Request({self.request_id[:8]}|{self.request_type}|P{self.priority})"