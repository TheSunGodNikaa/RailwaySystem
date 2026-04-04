import os
import sys
import time
import random

# Add current directory to Python path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from main import MIQSMModule


def _sleep_with_interrupt(seconds):
    """Sleep in small chunks so Ctrl+C interrupts quickly."""
    end_time = time.time() + seconds
    while time.time() < end_time:
        time.sleep(min(0.2, end_time - time.time()))


def generate_sample_requests(count=100):
    """Generate sample booking/cancellation/payment requests."""
    requests = []
    request_types = ["booking", "cancellation", "payment"]

    for _ in range(count):
        req_type = random.choice(request_types)

        if req_type == "booking":
            request = {
                "type": "booking",
                "user_id": f"USER_{random.randint(1000, 9999)}",
                "train_id": f"T{random.randint(10000, 99999)}",
                "seat_count": random.randint(1, 4),
                "priority": random.choice([1, 2, 3]),
            }
        elif req_type == "cancellation":
            request = {
                "type": "cancellation",
                "user_id": f"USER_{random.randint(1000, 9999)}",
                "booking_id": f"B{random.randint(10000, 99999)}",
                "priority": 1,
            }
        else:
            request = {
                "type": "payment",
                "user_id": f"USER_{random.randint(1000, 9999)}",
                "amount": random.randint(500, 5000),
                "booking_id": f"B{random.randint(10000, 99999)}",
                "priority": 3,
            }

        requests.append(request)

    return requests


def demo_scenario_1_normal_load():
    """Demo Scenario 1: Normal load (50 requests)."""
    print("\n" + "=" * 60)
    print("SCENARIO 1: NORMAL LOAD")
    print("=" * 60 + "\n")

    module = MIQSMModule()
    try:
        module.initialize()
        module.start_processing()

        requests = generate_sample_requests(50)
        assigned, failed = module.collect_and_enqueue_requests(requests)
        print(f"\nAssigned: {assigned}, Failed: {failed}")

        print("\nProcessing requests... (Press Ctrl+C to stop)")
        _sleep_with_interrupt(5)

        status = module.get_system_status()
        print("\nSystem Status:")
        print(f"  - Requests in queues: {status['total_requests_in_queues']}")
        print(f"  - Total processed: {status['total_processed']}")

        validation = module.run_validation_checks()
        print("\nValidation Results:")
        print(f"  - Anomalies detected: {len(validation['anomalies'])}")
        print(f"  - Starvation cases: {len(validation['starvation'])}")
    finally:
        module.shutdown()


def demo_scenario_2_burst_traffic():
    """Demo Scenario 2: Burst traffic (200 requests in short time)."""
    print("\n" + "=" * 60)
    print("SCENARIO 2: BURST TRAFFIC (Tatkal simulation)")
    print("=" * 60 + "\n")

    module = MIQSMModule()
    try:
        module.initialize()
        module.start_processing()

        requests = generate_sample_requests(200)
        for req in requests[:150]:
            if req["type"] == "booking":
                req["priority"] = 3

        assigned, failed = module.collect_and_enqueue_requests(requests)
        print(f"\nAssigned: {assigned}, Failed: {failed}")

        print("\nProcessing burst traffic... (Press Ctrl+C to stop)")
        for i in range(10):
            _sleep_with_interrupt(1)
            status = module.get_system_status()
            print(
                f"  [{i + 1}s] In queue: {status['total_requests_in_queues']}, "
                f"Processed: {status['total_processed']}"
            )

        validation = module.run_validation_checks()
        print("\nValidation Results:")
        print(f"  - Anomalies: {len(validation['anomalies'])}")
        if validation["anomalies"]:
            for anomaly in validation["anomalies"][:3]:
                print(f"  - {anomaly['type']}: {anomaly['message']}")
    finally:
        module.shutdown()


def demo_scenario_3_continuous_operation():
    """Demo Scenario 3: Continuous operation with periodic arrivals."""
    print("\n" + "=" * 60)
    print("SCENARIO 3: CONTINUOUS OPERATION")
    print("=" * 60 + "\n")

    module = MIQSMModule()
    try:
        module.initialize()
        module.start_processing()

        print("Running continuous operation for 15 seconds...")
        print("Requests will arrive in batches every 3 seconds.")
        print("Press Ctrl+C to stop.\n")

        for batch in range(5):
            requests = generate_sample_requests(30)
            assigned, failed = module.collect_and_enqueue_requests(requests)

            status = module.get_system_status()
            print(
                f"[Batch {batch + 1}] Assigned: {assigned}, "
                f"In queue: {status['total_requests_in_queues']}, "
                f"Processed: {status['total_processed']}"
            )

            _sleep_with_interrupt(3)

        print("\nFinal Metrics:")
        status = module.get_system_status()
        print(f"  - Total processed: {status['total_processed']}")
        print(f"  - Remaining in queue: {status['total_requests_in_queues']}")

        for queue_stat in status["queues"]:
            print(
                f"  - Queue {queue_stat['queue_id']}: "
                f"Served {queue_stat['total_served']}, "
                f"Avg wait: {queue_stat['avg_waiting_time']:.2f}s"
            )
    finally:
        module.shutdown()


def main():
    """Main demo function."""
    print("\n" + "=" * 60)
    print("MULTI-INPUT QUEUE STOCHASTIC MODELLING")
    print("Railway Reservation System - Module 1 Demo")
    print("=" * 60)

    print("\nAvailable Scenarios:")
    print("1. Normal Load (50 requests)")
    print("2. Burst Traffic - Tatkal Simulation (200 requests)")
    print("3. Continuous Operation (5 batches of 30 requests)")
    print("4. Run All Scenarios")

    choice = input("\nSelect scenario (1-4): ").strip()

    if choice == "1":
        demo_scenario_1_normal_load()
    elif choice == "2":
        demo_scenario_2_burst_traffic()
    elif choice == "3":
        demo_scenario_3_continuous_operation()
    elif choice == "4":
        print("\nRunning all scenarios sequentially...\n")
        demo_scenario_1_normal_load()
        _sleep_with_interrupt(2)
        demo_scenario_2_burst_traffic()
        _sleep_with_interrupt(2)
        demo_scenario_3_continuous_operation()
    else:
        print("Invalid choice. Running Scenario 1 by default.")
        demo_scenario_1_normal_load()

    print("\n" + "=" * 60)
    print("DEMO COMPLETE")
    print("=" * 60)
    print("\nCheck the 'logs' folder for detailed execution logs!")


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\nInterrupted by user (Ctrl+C). Exiting cleanly...")
