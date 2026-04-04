import time
import random
from main import MIQSMModule

def generate_sample_requests(count=100):
    """Generate sample booking/cancellation/payment requests"""
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
        else:  # payment
            request = {
                'type': 'payment',
                'user_id': f'USER_{random.randint(1000, 9999)}',
                'amount': random.randint(500, 5000),
                'booking_id': f'B{random.randint(10000, 99999)}',
                'priority': 3
            }
        
        requests.append(request)
    
    return requests

def demo_scenario_1_normal_load():
    """Demo Scenario 1: Normal load (50 requests)"""
    print("\n" + "="*60)
    print("SCENARIO 1: NORMAL LOAD")
    print("="*60 + "\n")
    
    module = MIQSMModule()
    module.initialize()
    module.start_processing()
    
    # Generate and process requests
    requests = generate_sample_requests(50)
    assigned, failed = module.collect_and_enqueue_requests(requests)
    
    print(f"\n✓ Assigned: {assigned}, Failed: {failed}")
    
    # Let it process for a few seconds
    print("\nProcessing requests...")
    time.sleep(5)
    
    # Check status
    status = module.get_system_status()
    print(f"\nSystem Status:")
    print(f"  - Requests in queues: {status['total_requests_in_queues']}")
    print(f"  - Total processed: {status['total_processed']}")
    
    # Run validations
    validation = module.run_validation_checks()
    print(f"\nValidation Results:")
    print(f"  - Anomalies detected: {len(validation['anomalies'])}")
    print(f"  - Starvation cases: {len(validation['starvation'])}")
    
    module.shutdown()
    return module

def demo_scenario_2_burst_traffic():
    """Demo Scenario 2: Burst traffic (200 requests in short time)"""
    print("\n" + "="*60)
    print("SCENARIO 2: BURST TRAFFIC (Tatkal simulation)")
    print("="*60 + "\n")
    
    module = MIQSMModule()
    module.initialize()
    module.start_processing()
    
    # Generate burst of requests
    requests = generate_sample_requests(200)
    
    # Make most of them high priority (Tatkal)
    for req in requests[:150]:
        if req['type'] == 'booking':
            req['priority'] = 3
    
    assigned, failed = module.collect_and_enqueue_requests(requests)
    
    print(f"\n✓ Assigned: {assigned}, Failed: {failed}")
    
    # Process for longer
    print("\nProcessing burst traffic...")
    for i in range(10):
        time.sleep(1)
        status = module.get_system_status()
        print(f"  [{i+1}s] In queue: {status['total_requests_in_queues']}, Processed: {status['total_processed']}")
    
    # Final validation
    validation = module.run_validation_checks()
    print(f"\nValidation Results:")
    print(f"  - Anomalies: {len(validation['anomalies'])}")
    if validation['anomalies']:
        for anomaly in validation['anomalies'][:3]:
            print(f"    • {anomaly['type']}: {anomaly['message']}")
    
    module.shutdown()
    return module

def demo_scenario_3_continuous_operation():
    """Demo Scenario 3: Continuous operation with periodic arrivals"""
    print("\n" + "="*60)
    print("SCENARIO 3: CONTINUOUS OPERATION")
    print("="*60 + "\n")
    
    module = MIQSMModule()
    module.initialize()
    module.start_processing()
    
    print("Running continuous operation for 15 seconds...")
    print("Requests will arrive in batches every 3 seconds\n")
    
    for batch in range(5):
        # Generate periodic batch
        requests = generate_sample_requests(30)
        assigned, failed = module.collect_and_enqueue_requests(requests)
        
        status = module.get_system_status()
        print(f"[Batch {batch+1}] Assigned: {assigned}, In queue: {status['total_requests_in_queues']}, Processed: {status['total_processed']}")
        
        time.sleep(3)
    
    # Final metrics
    print("\nFinal Metrics:")
    status = module.get_system_status()
    print(f"  - Total processed: {status['total_processed']}")
    print(f"  - Remaining in queue: {status['total_requests_in_queues']}")
    
    for queue_stat in status['queues']:
        print(f"  - Queue {queue_stat['queue_id']}: Served {queue_stat['total_served']}, Avg wait: {queue_stat['avg_waiting_time']:.2f}s")
    
    module.shutdown()
    return module

def main():
    """Main demo function"""
    print("\n" + "="*60)
    print("MULTI-INPUT QUEUE STOCHASTIC MODELLING")
    print("Railway Reservation System - Module 1 Demo")
    print("="*60)
    
    print("\nAvailable Scenarios:")
    print("1. Normal Load (50 requests)")
    print("2. Burst Traffic - Tatkal Simulation (200 requests)")
    print("3. Continuous Operation (5 batches of 30 requests)")
    print("4. Run All Scenarios")
    
    choice = input("\nSelect scenario (1-4): ").strip()
    
    if choice == '1':
        demo_scenario_1_normal_load()
    elif choice == '2':
        demo_scenario_2_burst_traffic()
    elif choice == '3':
        demo_scenario_3_continuous_operation()
    elif choice == '4':
        print("\nRunning all scenarios sequentially...\n")
        demo_scenario_1_normal_load()
        time.sleep(2)
        demo_scenario_2_burst_traffic()
        time.sleep(2)
        demo_scenario_3_continuous_operation()
    else:
        print("Invalid choice. Running Scenario 1 by default.")
        demo_scenario_1_normal_load()
    
    print("\n" + "="*60)
    print("DEMO COMPLETE")
    print("="*60)
    print("\nCheck the 'logs' folder for detailed execution logs!")

if __name__ == "__main__":
    main()