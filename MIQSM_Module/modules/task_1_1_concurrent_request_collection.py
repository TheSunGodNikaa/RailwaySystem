import time
import threading
import sys
import os
from collections import defaultdict

# Add project root to path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from config.constants import (
    REQUEST_TYPE_BOOKING,
    REQUEST_TYPE_CANCELLATION,
    REQUEST_TYPE_PAYMENT,
    PRIORITY_HIGH,
    PRIORITY_MEDIUM,
    PRIORITY_LOW,
    BURST_THRESHOLD,
)

from models.request import BookingRequest
from utils.logger import MIQSMLogger
from utils.metrics import MetricsCollector


class ConcurrentRequestCollection:
    """Task 1.1: Concurrent Request Collection"""

    def __init__(self, logger, metrics):
        self.logger = logger
        self.metrics = metrics
        self.requests_buffer = []
        self.buffer_lock = threading.Lock()
        self.arrival_stats = defaultdict(int)
        self.arrival_window_start = time.time()
        self.window_duration = 1.0  # 1 second window

    # ========== ACTIVITY 1.1.1: Request Intake ==========

    def capture_booking_request(self, user_id, train_id, seat_count, priority=PRIORITY_MEDIUM):
        """Program 1.1.1.1: Capture booking requests"""
        request = BookingRequest(
            request_type=REQUEST_TYPE_BOOKING,
            user_id=user_id,
            train_id=train_id,
            seat_count=seat_count,
            priority=priority,
            metadata={"operation": "booking"},
        )
        self._intake_request(request)
        self.logger.info(f"Captured booking request: {request.request_id} for user {user_id}")
        return request

    def capture_cancellation_request(self, user_id, booking_id, priority=PRIORITY_LOW):
        """Program 1.1.1.2: Capture cancellation requests"""
        request = BookingRequest(
            request_type=REQUEST_TYPE_CANCELLATION,
            user_id=user_id,
            priority=priority,
            metadata={"operation": "cancellation", "booking_id": booking_id},
        )
        self._intake_request(request)
        self.logger.info(f"Captured cancellation request: {request.request_id} for booking {booking_id}")
        return request

    def capture_payment_initiation_request(self, user_id, amount, booking_id, priority=PRIORITY_HIGH):
        """Program 1.1.1.3: Capture payment initiation requests"""
        request = BookingRequest(
            request_type=REQUEST_TYPE_PAYMENT,
            user_id=user_id,
            priority=priority,
            metadata={"operation": "payment", "amount": amount, "booking_id": booking_id},
        )
        self._intake_request(request)
        self.logger.info(f"Captured payment request: {request.request_id} for Rs.{amount}")
        return request

    def _intake_request(self, request):
        """Internal method to add request to buffer"""
        with self.buffer_lock:
            self.requests_buffer.append(request)
            self.arrival_stats[int(time.time())] += 1

    # ========== ACTIVITY 1.1.2: Request Classification ==========

    def identify_request_type(self, request):
        """Program 1.1.2.1: Identify request type"""
        request_type = request.request_type
        self.logger.debug(f"Request {request.request_id} identified as {request_type}")
        return request_type

    def assign_request_priority(self, request):
        """Program 1.1.2.2: Assign request priority"""
        if request.request_type == REQUEST_TYPE_PAYMENT:
            request.priority = PRIORITY_HIGH
        elif request.request_type == REQUEST_TYPE_BOOKING:
            if request.metadata.get("tatkal", False):
                request.priority = PRIORITY_HIGH
            else:
                request.priority = PRIORITY_MEDIUM
        else:
            request.priority = PRIORITY_LOW

        self.logger.debug(f"Request {request.request_id} assigned priority {request.priority}")
        return request.priority

    def tag_request_metadata(self, request):
        """Program 1.1.2.3: Tag request metadata"""
        request.metadata["classified_at"] = time.time()
        request.metadata["source_ip"] = f"192.168.1.{hash(request.user_id) % 255}"
        request.metadata["session_id"] = f"sess_{request.user_id}_{int(time.time())}"

        self.logger.debug(f"Request {request.request_id} tagged with metadata")
        return request.metadata

    # ========== ACTIVITY 1.1.3: Arrival Pattern Analysis ==========

    def monitor_request_arrival_rate(self):
        """Program 1.1.3.1: Monitor request arrival rate"""
        current_time = time.time()
        
        elapsed = current_time - self.arrival_window_start

        if elapsed >= self.window_duration:
            with self.buffer_lock:
                current_count = len(self.requests_buffer)

            arrival_rate = current_count / elapsed

            if hasattr(self.metrics, "record_arrival_rate"):
                self.metrics.record_arrival_rate(arrival_rate, current_time)

            self.logger.info(f"Arrival rate: {arrival_rate:.2f} requests/sec")
            self.arrival_window_start = current_time
            return arrival_rate

        return None

    def identify_burst_traffic(self):
        """Program 1.1.3.2: Identify burst traffic"""
        arrival_rate = self.monitor_request_arrival_rate()

        if arrival_rate and arrival_rate > BURST_THRESHOLD:
            with self.buffer_lock:
                burst_count = len(self.requests_buffer)

            if hasattr(self.metrics, "record_burst_event"):
                self.metrics.record_burst_event(burst_count)

            self.logger.warning(f"BURST DETECTED! Rate: {arrival_rate:.2f} req/s")
            return True, burst_count

        return False, 0

    def log_arrival_statistics(self):
        """Program 1.1.3.3: Log arrival statistics"""
        with self.buffer_lock:
            total_requests = len(self.requests_buffer)
            type_distribution = defaultdict(int)
            priority_distribution = defaultdict(int)

            for req in self.requests_buffer:
                type_distribution[req.request_type] += 1
                priority_distribution[req.priority] += 1

        stats = {
            "total_requests": total_requests,
            "type_distribution": dict(type_distribution),
            "priority_distribution": dict(priority_distribution),
            "timestamp": time.time(),
        }

        self.logger.info(f"Arrival Statistics: {stats}")
        return stats

    def get_all_requests(self):
        """Get all collected requests"""
        with self.buffer_lock:
            requests = self.requests_buffer.copy()
            self.requests_buffer.clear()
        return requests
