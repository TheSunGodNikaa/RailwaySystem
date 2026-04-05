<?php

function railwayGetStations($conn): array
{
    $sql = "
        SELECT station_name
        FROM (
            SELECT source_station AS station_name FROM trains
            UNION
            SELECT destination_station AS station_name FROM trains
            UNION
            SELECT station_name FROM train_station
        )
        ORDER BY station_name
    ";

    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);

    $stations = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $stations[] = $row['STATION_NAME'];
    }

    return $stations;
}

function railwaySearchTrains($conn, string $source, string $destination, ?string $selectedTrainId = null): array
{
    $source = trim($source);
    $destination = trim($destination);

    if ($source === '' || $destination === '') {
        return [];
    }

    $sql = "
        SELECT *
        FROM (
            SELECT
                t.train_id,
                t.train_number,
                t.train_name,
                t.source_station,
                t.destination_station,
                t.departure_time,
                t.arrival_time,
                t.duration,
                t.train_type,
                t.price,
                NVL(src.stop_number, 0) AS src_stop_number,
                CASE
                    WHEN UPPER(t.destination_station) = UPPER(:destination)
                        THEN 999999
                    ELSE NVL(dst.stop_number, -1)
                END AS dst_stop_number,
                CASE
                    WHEN UPPER(t.source_station) = UPPER(:source)
                        THEN t.departure_time
                    ELSE src.departure_time
                END AS board_time,
                CASE
                    WHEN UPPER(t.destination_station) = UPPER(:destination)
                        THEN t.arrival_time
                    ELSE dst.arrival_time
                END AS drop_time
            FROM trains t
            LEFT JOIN train_station src
                ON t.train_id = src.train_id
                AND UPPER(src.station_name) = UPPER(:source)
            LEFT JOIN train_station dst
                ON t.train_id = dst.train_id
                AND UPPER(dst.station_name) = UPPER(:destination)
            WHERE
                (UPPER(t.source_station) = UPPER(:source) OR src.station_name IS NOT NULL)
                AND
                (UPPER(t.destination_station) = UPPER(:destination) OR dst.station_name IS NOT NULL)
        )
        WHERE src_stop_number < dst_stop_number
            AND (:selected_train_id IS NULL OR TO_CHAR(train_id) = :selected_train_id)
        ORDER BY train_id
    ";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":source", $source);
    oci_bind_by_name($stmt, ":destination", $destination);
    $selectedTrainBind = ($selectedTrainId !== null && $selectedTrainId !== '') ? $selectedTrainId : null;
    oci_bind_by_name($stmt, ":selected_train_id", $selectedTrainBind);
    oci_execute($stmt);

    $results = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $results[] = $row;
    }

    return $results;
}

function railwayGetTrainById($conn, $trainId): ?array
{
    $sql = "
        SELECT
            train_id,
            train_number,
            train_name,
            source_station,
            destination_station,
            departure_time,
            arrival_time,
            duration,
            train_type,
            price
        FROM trains
        WHERE train_id = :train_id
    ";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":train_id", $trainId);
    oci_execute($stmt);

    $row = oci_fetch_assoc($stmt);

    return $row ?: null;
}

function railwayRoundFare(float $amount): int
{
    return (int) (round($amount / 10) * 10);
}

function railwayGetClassCatalog(float $basePrice): array
{
    $basePrice = $basePrice > 0 ? $basePrice : 1200;

    return [
        'GN' => ['label' => 'General', 'price' => max(80, railwayRoundFare($basePrice * 0.35)), 'icon' => 'GN'],
        'SL' => ['label' => 'Sleeper', 'price' => max(150, railwayRoundFare($basePrice * 0.58)), 'icon' => 'SL'],
        'CC' => ['label' => 'Chair Car', 'price' => max(200, railwayRoundFare($basePrice * 0.82)), 'icon' => 'CC'],
        '3A' => ['label' => 'AC 3 Tier', 'price' => railwayRoundFare($basePrice), 'icon' => '3A'],
        'EC' => ['label' => 'Executive Chair Car', 'price' => railwayRoundFare($basePrice * 1.22), 'icon' => 'EC'],
        '2A' => ['label' => 'AC 2 Tier', 'price' => railwayRoundFare($basePrice * 1.42), 'icon' => '2A'],
    ];
}

function railwayGetTrainClassAvailability($conn, $trainId): array
{
    $train = railwayGetTrainById($conn, $trainId);
    $basePrice = (float) ($train['PRICE'] ?? 0);
    $catalog = railwayGetClassCatalog($basePrice);

    $sql = "
        SELECT
            compartment,
            COUNT(*) AS total_seats,
            SUM(CASE WHEN status = 'AVAILABLE' THEN 1 ELSE 0 END) AS available_seats
        FROM train_seats
        WHERE train_id = :train_id
        GROUP BY compartment
        ORDER BY compartment
    ";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":train_id", $trainId);
    oci_execute($stmt);

    $classes = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $code = $row['COMPARTMENT'];
        $classInfo = $catalog[$code] ?? [
            'label' => $code,
            'price' => railwayRoundFare($basePrice),
            'icon' => $code,
        ];

        $classes[] = [
            'code' => $code,
            'label' => $classInfo['label'],
            'price' => $classInfo['price'],
            'icon' => $classInfo['icon'],
            'total_seats' => (int) $row['TOTAL_SEATS'],
            'available_seats' => (int) $row['AVAILABLE_SEATS'],
        ];
    }

    return $classes;
}

function railwayEnsureBookingHistoryTable($conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $plsql = "
        DECLARE
            table_count NUMBER := 0;
        BEGIN
            SELECT COUNT(*)
            INTO table_count
            FROM user_tables
            WHERE table_name = 'PASSENGER_BOOKING_HISTORY';

            IF table_count = 0 THEN
                EXECUTE IMMEDIATE '
                    CREATE TABLE PASSENGER_BOOKING_HISTORY (
                        booking_id NUMBER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                        user_id NUMBER NOT NULL,
                        transaction_id VARCHAR2(50),
                        train_id NUMBER NOT NULL,
                        train_number VARCHAR2(20),
                        train_name VARCHAR2(100),
                        source_station VARCHAR2(100),
                        destination_station VARCHAR2(100),
                        board_time VARCHAR2(20),
                        drop_time VARCHAR2(20),
                        compartment VARCHAR2(20),
                        seats VARCHAR2(200),
                        seat_count NUMBER,
                        fare_per_seat NUMBER(10,2),
                        total_amount NUMBER(10,2),
                        journey_date VARCHAR2(20),
                        booking_status VARCHAR2(30),
                        booked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ';
            END IF;
        END;
    ";

    $stmt = oci_parse($conn, $plsql);
    @oci_execute($stmt);
    $initialized = true;
}

function railwayInsertPassengerBookingHistory($conn, array $booking): void
{
    railwayEnsureBookingHistoryTable($conn);

    $sql = "
        INSERT INTO PASSENGER_BOOKING_HISTORY (
            user_id,
            transaction_id,
            train_id,
            train_number,
            train_name,
            source_station,
            destination_station,
            board_time,
            drop_time,
            compartment,
            seats,
            seat_count,
            fare_per_seat,
            total_amount,
            journey_date,
            booking_status
        ) VALUES (
            :user_id,
            :transaction_id,
            :train_id,
            :train_number,
            :train_name,
            :source_station,
            :destination_station,
            :board_time,
            :drop_time,
            :compartment,
            :seats,
            :seat_count,
            :fare_per_seat,
            :total_amount,
            :journey_date,
            :booking_status
        )
    ";

    $stmt = oci_parse($conn, $sql);
    foreach ($booking as $key => $value) {
        oci_bind_by_name($stmt, ":" . $key, $booking[$key]);
    }
    @oci_execute($stmt, OCI_NO_AUTO_COMMIT);
}

function railwayGetPassengerBookingHistory($conn, $userId): array
{
    railwayEnsureBookingHistoryTable($conn);

    $sql = "
        SELECT
            booking_id,
            transaction_id,
            train_id,
            train_number,
            train_name,
            source_station,
            destination_station,
            board_time,
            drop_time,
            compartment,
            seats,
            seat_count,
            fare_per_seat,
            total_amount,
            journey_date,
            booking_status,
            booked_at
        FROM PASSENGER_BOOKING_HISTORY
        WHERE user_id = :user_id
        ORDER BY booked_at DESC
    ";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":user_id", $userId);
    @oci_execute($stmt);

    $history = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $history[] = $row;
    }

    return $history;
}

function railwayEnsureCancellationRequestsTable($conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $plsql = "
        DECLARE
            table_count NUMBER := 0;
        BEGIN
            SELECT COUNT(*)
            INTO table_count
            FROM user_tables
            WHERE table_name = 'BOOKING_CANCELLATION_REQUESTS';

            IF table_count = 0 THEN
                EXECUTE IMMEDIATE '
                    CREATE TABLE BOOKING_CANCELLATION_REQUESTS (
                        request_id NUMBER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                        user_id NUMBER NOT NULL,
                        passenger_name VARCHAR2(120),
                        transaction_id VARCHAR2(50) NOT NULL,
                        reason VARCHAR2(500),
                        request_status VARCHAR2(30) DEFAULT ''PENDING'',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ';
            END IF;
        END;
    ";

    $stmt = oci_parse($conn, $plsql);
    @oci_execute($stmt);
    $initialized = true;
}

function railwayInsertCancellationRequest($conn, array $payload): void
{
    railwayEnsureCancellationRequestsTable($conn);

    $sql = "
        INSERT INTO BOOKING_CANCELLATION_REQUESTS (
            user_id,
            passenger_name,
            transaction_id,
            reason,
            request_status
        ) VALUES (
            :user_id,
            :passenger_name,
            :transaction_id,
            :reason,
            :request_status
        )
    ";

    $stmt = oci_parse($conn, $sql);
    foreach ($payload as $key => $value) {
        oci_bind_by_name($stmt, ":" . $key, $payload[$key]);
    }
    @oci_execute($stmt, OCI_NO_AUTO_COMMIT);
}

function railwayGetCancellationRequests($conn): array
{
    railwayEnsureCancellationRequestsTable($conn);

    $sql = "
        SELECT
            request_id,
            user_id,
            passenger_name,
            transaction_id,
            reason,
            request_status,
            created_at
        FROM BOOKING_CANCELLATION_REQUESTS
        ORDER BY created_at DESC
    ";

    $stmt = oci_parse($conn, $sql);
    @oci_execute($stmt);

    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $rows[] = $row;
    }

    return $rows;
}

function railwayResolveCancellationRequest($conn, string $transactionId): void
{
    railwayEnsureCancellationRequestsTable($conn);

    $sql = "
        UPDATE BOOKING_CANCELLATION_REQUESTS
        SET request_status = 'RESOLVED'
        WHERE transaction_id = :transaction_id
    ";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":transaction_id", $transactionId);
    @oci_execute($stmt, OCI_NO_AUTO_COMMIT);
}

function railwayEnsureClerkStatusTable($conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $plsql = "
        DECLARE
            table_count NUMBER := 0;
        BEGIN
            SELECT COUNT(*)
            INTO table_count
            FROM user_tables
            WHERE table_name = 'CLERK_LOGIN_STATUS';

            IF table_count = 0 THEN
                EXECUTE IMMEDIATE '
                    CREATE TABLE CLERK_LOGIN_STATUS (
                        username VARCHAR2(100) PRIMARY KEY,
                        is_logged_in NUMBER(1) DEFAULT 0,
                        last_login_at TIMESTAMP,
                        last_logout_at TIMESTAMP
                    )
                ';
            END IF;
        END;
    ";

    $stmt = oci_parse($conn, $plsql);
    @oci_execute($stmt);
    $initialized = true;
}

function railwayUpsertClerkLoginStatus($conn, string $username, bool $isLoggedIn): void
{
    railwayEnsureClerkStatusTable($conn);

    $loggedValue = $isLoggedIn ? 1 : 0;
    $sql = "
        MERGE INTO CLERK_LOGIN_STATUS target
        USING (SELECT :username AS username FROM dual) src
        ON (target.username = src.username)
        WHEN MATCHED THEN
            UPDATE SET
                is_logged_in = :logged_value,
                last_login_at = CASE WHEN :logged_value = 1 THEN CURRENT_TIMESTAMP ELSE target.last_login_at END,
                last_logout_at = CASE WHEN :logged_value = 0 THEN CURRENT_TIMESTAMP ELSE target.last_logout_at END
        WHEN NOT MATCHED THEN
            INSERT (username, is_logged_in, last_login_at, last_logout_at)
            VALUES (
                :username,
                :logged_value,
                CASE WHEN :logged_value = 1 THEN CURRENT_TIMESTAMP ELSE NULL END,
                CASE WHEN :logged_value = 0 THEN CURRENT_TIMESTAMP ELSE NULL END
            )
    ";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":username", $username);
    oci_bind_by_name($stmt, ":logged_value", $loggedValue);
    @oci_execute($stmt, OCI_NO_AUTO_COMMIT);
}

function railwayGetClerkStatuses($conn): array
{
    railwayEnsureClerkStatusTable($conn);

    $sql = "
        SELECT
            c.CLERK_ID,
            c.EMP_ID,
            c.USERNAME,
            c.FULL_NAME,
            c.EMAIL,
            c.DESIGNATION,
            c.DEPARTMENT,
            c.STATION_CODE,
            c.CREATED_AT,
            NVL(s.is_logged_in, 0) AS is_logged_in,
            s.last_login_at,
            s.last_logout_at
        FROM CLERK c
        LEFT JOIN CLERK_LOGIN_STATUS s
            ON s.username = c.username
        ORDER BY c.CREATED_AT DESC
    ";

    $stmt = oci_parse($conn, $sql);
    @oci_execute($stmt);

    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $rows[] = $row;
    }

    return $rows;
}
