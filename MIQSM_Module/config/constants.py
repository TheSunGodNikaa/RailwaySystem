# Configuration constants for MIQSM Module

# Request Types
REQUEST_TYPE_BOOKING = "BOOKING"
REQUEST_TYPE_CANCELLATION = "CANCELLATION"
REQUEST_TYPE_PAYMENT = "PAYMENT"

# Priority Levels
PRIORITY_HIGH = 3      # Tatkal, Emergency
PRIORITY_MEDIUM = 2    # Normal booking
PRIORITY_LOW = 1       # Cancellation, Queries

# Queue Configuration
MAX_QUEUE_SIZE = 1000
NUM_INPUT_QUEUES = 5
BURST_THRESHOLD = 50   # requests per second

# Scheduling Parameters
STOCHASTIC_WEIGHT_HIGH = 0.6
STOCHASTIC_WEIGHT_MEDIUM = 0.3
STOCHASTIC_WEIGHT_LOW = 0.1

# Monitoring Thresholds
MAX_WAITING_TIME = 30  # seconds
STARVATION_THRESHOLD = 100  # requests waiting