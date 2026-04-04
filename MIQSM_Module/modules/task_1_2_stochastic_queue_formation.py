import random
import numpy as np
import time
import sys
import os

# Add parent directory to path for imports
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from config.constants import (
    NUM_INPUT_QUEUES, MAX_QUEUE_SIZE, STARVATION_THRESHOLD,
    STOCHASTIC_WEIGHT_HIGH, STOCHASTIC_WEIGHT_MEDIUM, STOCHASTIC_WEIGHT_LOW
)
from models.queue import InputQueue
from utils.logger import MIQSMLogger
from utils.metrics import MetricsCollector

class StochasticQueueFormation:
    """Task 1.2: Stochastic Queue Formation"""
    
    def __init__(self, logger, metrics, num_queues=NUM_INPUT_QUEUES):
        self.logger = logger
        self.metrics = metrics
        self.num_queues = num_queues
        self.queues = []
        self.service_count = {i: 0 for i in range(num_queues)}
        
    # ========== ACTIVITY 1.2.1: Queue Creation ==========
    
    def initialize_input_queues(self):
        """Program 1.2.1.1: Initialize input queues"""
        self.queues = []
        for i in range(self.num_queues):
            queue = InputQueue(queue_id=i, max_size=MAX_QUEUE_SIZE)
            self.queues.append(queue)
            self.logger.info(f"Initialized Queue {i} with max size {MAX_QUEUE_SIZE}")
        
        return self.queues
    
    def assign_requests_to_queues(self, requests):
        """Program 1.2.1.2: Assign requests to queues"""
        assigned_count = 0
        failed_count = 0
        
        for request in requests:
            # Use hash-based distribution for load balancing
            queue_index = hash(request.user_id) % self.num_queues
            
            # Try to assign to calculated queue
            if self.queues[queue_index].enqueue(request):
                assigned_count += 1
                self.logger.debug(f"Assigned {request.request_id} to Queue {queue_index}")
            else:
                # Queue full, try next available queue
                assigned = False
                for i in range(self.num_queues):
                    alternate_queue = (queue_index + i) % self.num_queues
                    if self.queues[alternate_queue].enqueue(request):
                        assigned_count += 1
                        assigned = True
                        self.logger.debug(f"Assigned {request.request_id} to alternate Queue {alternate_queue}")
                        break
                
                if not assigned:
                    failed_count += 1
                    self.logger.error(f"Failed to assign {request.request_id} - all queues full")
        
        self.logger.info(f"Assignment complete: {assigned_count} assigned, {failed_count} failed")
        return assigned_count, failed_count
    
    def maintain_queue_states(self):
        """Program 1.2.1.3: Maintain queue states"""
        queue_states = []
        
        for queue in self.queues:
            state = queue.get_stats()
            queue_states.append(state)
            self.metrics.record_queue_length(queue.queue_id, state['current_size'])
        
        self.logger.debug(f"Queue states maintained: {queue_states}")
        return queue_states
    
    # ========== ACTIVITY 1.2.2: Probabilistic Scheduling ==========
    
    def compute_scheduling_probability(self):
        """Program 1.2.2.1: Compute scheduling probability"""
        # Calculate probability distribution based on queue sizes and priorities
        queue_sizes = [q.size() for q in self.queues]
        total_requests = sum(queue_sizes)
        
        if total_requests == 0:
            # Equal probability if all queues empty
            probabilities = [1.0 / self.num_queues] * self.num_queues
        else:
            # Probability inversely proportional to service count (fairness)
            # and proportional to queue size (urgency)
            raw_probs = []
            for i, queue in enumerate(self.queues):
                size_factor = queue.size() + 1  # Avoid zero
                service_factor = 1.0 / (self.service_count[i] + 1)  # Fairness boost
                raw_probs.append(size_factor * service_factor)
            
            # Normalize to sum to 1
            total = sum(raw_probs)
            probabilities = [p / total for p in raw_probs]
        
        self.logger.debug(f"Scheduling probabilities: {probabilities}")
        return probabilities
    
    def select_next_request(self):
        """Program 1.2.2.2: Select next request"""
        probabilities = self.compute_scheduling_probability()
        
        # Stochastic selection based on computed probabilities
        selected_queue_idx = np.random.choice(self.num_queues, p=probabilities)
        
        # Peek at the next request without removing
        selected_queue = self.queues[selected_queue_idx]
        next_request = selected_queue.peek()
        
        if next_request:
            self.logger.debug(f"Selected request {next_request.request_id} from Queue {selected_queue_idx}")
        else:
            self.logger.debug(f"Queue {selected_queue_idx} is empty, reselecting...")
            # Try other non-empty queues
            for i in range(self.num_queues):
                if not self.queues[i].is_empty():
                    next_request = self.queues[i].peek()
                    selected_queue_idx = i
                    break
        
        return next_request, selected_queue_idx
    
    def dispatch_request(self, queue_index):
        """Program 1.2.2.3: Dispatch request"""
        if queue_index < 0 or queue_index >= self.num_queues:
            self.logger.error(f"Invalid queue index: {queue_index}")
            return None
        
        request = self.queues[queue_index].dequeue()
        
        if request:
            self.service_count[queue_index] += 1
            request.start_processing()
            self.logger.info(f"Dispatched request {request.request_id} from Queue {queue_index}")
        
        return request
    
    # ========== ACTIVITY 1.2.3: Fairness Enforcement ==========
    
    def prevent_request_starvation(self):
        """Program 1.2.3.1: Prevent request starvation"""
        starved_requests = []
        
        for queue in self.queues:
            if queue.is_empty():
                continue
            
            oldest_request = queue.peek()
            waiting_time = oldest_request.get_waiting_time()
            
            if waiting_time > STARVATION_THRESHOLD:
                starved_requests.append({
                    'request_id': oldest_request.request_id,
                    'queue_id': queue.queue_id,
                    'waiting_time': waiting_time
                })
                self.logger.warning(f"Starvation detected! Request {oldest_request.request_id} waiting {waiting_time:.2f}s")
        
        # Boost priority for starved requests
        if starved_requests:
            self.metrics.record_anomaly('STARVATION', f"{len(starved_requests)} requests starving")
        
        return starved_requests
    
    def balance_queue_service(self):
        """Program 1.2.3.2: Balance queue service"""
        # Calculate service imbalance
        service_counts = list(self.service_count.values())
        if not service_counts:
            return 0.0
        
        mean_service = np.mean(service_counts)
        std_service = np.std(service_counts)
        
        # Calculate coefficient of variation (lower is more balanced)
        balance_score = std_service / mean_service if mean_service > 0 else 0.0
        
        self.logger.debug(f"Queue balance score: {balance_score:.4f} (lower is better)")
        return balance_score
    
    def monitor_fairness_metrics(self):
        """Program 1.2.3.3: Monitor fairness metrics"""
        balance_score = self.balance_queue_service()
        starved_requests = self.prevent_request_starvation()
        
        fairness_metric = {
            'balance_score': balance_score,
            'starved_count': len(starved_requests),
            'service_distribution': self.service_count.copy(),
            'timestamp': time.time()
        }
        
        # Overall fairness score (0-1, higher is better)
        fairness_score = 1.0 / (1.0 + balance_score) if balance_score > 0 else 1.0
        if starved_requests:
            fairness_score *= 0.5  # Penalty for starvation
        
        self.metrics.record_fairness_score(fairness_score)
        self.logger.info(f"Fairness metrics: {fairness_metric}, Score: {fairness_score:.4f}")
        
        return fairness_metric