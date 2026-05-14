<?php
/**
 * POS Passengers API — Add new passengers manually
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Check authentication - return 401 if not authenticated
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = Auth::user();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleGet($user);
    exit;
}

if ($method === 'PUT') {
    handlePut($user);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$fullname = trim($input['fullname'] ?? '');
$mobileNumber = trim($input['mobile_number'] ?? '');
$email = trim($input['email'] ?? '');
$gender = trim($input['gender'] ?? '');
$birthDate = trim($input['birth_date'] ?? '');
$regionCode = trim($input['region_code'] ?? '');
$provinceCode = trim($input['province_code'] ?? '');
$cityCode = trim($input['city_municipality_code'] ?? '');
$barangayCode = trim($input['barangay_code'] ?? '');
$streetAddress = trim($input['street_address'] ?? '');
$notes = trim($input['notes'] ?? '');

// Debug logging
error_log('Passenger save data: ' . json_encode([
    'fullname' => $fullname,
    'mobile' => $mobileNumber,
    'region_code' => $regionCode,
    'province_code' => $provinceCode,
    'city_code' => $cityCode,
    'barangay_code' => $barangayCode,
    'street_address' => $streetAddress,
    'notes' => $notes
]));

// Validate
if (!$fullname) { echo json_encode(['success' => false, 'error' => 'Fullname is required.']); exit; }
// Mobile number is now optional, only validate if provided
if ($mobileNumber && !preg_match('/^09[0-9]{9}$/', $mobileNumber)) {
    echo json_encode(['success' => false, 'error' => 'Mobile number must be 11 digits starting with 09.']); exit;
}

try {
    // Check if passenger already exists by mobile number (only if mobile is provided)
    if ($mobileNumber) {
        $existing = Database::fetch(
            "SELECT passenger_id FROM passenger_accounts WHERE mobile_number = :mobile",
            ['mobile' => $mobileNumber]
        );

        if ($existing) {
            echo json_encode(['success' => false, 'error' => 'Passenger with this mobile number already exists.']);
            exit;
        }
    }

    // Generate passenger code
    $passengerCode = 'PAX-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

    // Insert new passenger
    Database::execute(
        "INSERT INTO passenger_accounts
            (fullname, mobile_number, email, gender, birth_date,
             region_code, province_code, city_municipality_code, barangay_code,
             street_address, notes, created_at, created_by)
         VALUES
            (:fullname, :mobile, :email, :gender, :birth_date,
             :region_code, :province_code, :city_code, :barangay_code,
             :street_address, :notes, NOW(), :created_by)",
        [
            'fullname'      => $fullname,
            'mobile'        => $mobileNumber ?: null,
            'email'         => $email ?: null,
            'gender'        => $gender ?: null,
            'birth_date'    => $birthDate ?: null,
            'region_code'   => $regionCode ?: null,
            'province_code' => $provinceCode ?: null,
            'city_code'     => $cityCode ?: null,
            'barangay_code' => $barangayCode ?: null,
            'street_address'=> $streetAddress ?: null,
            'notes'         => $notes ?: null,
            'created_by'    => $user['user_id'],
        ]
    );

    $passengerId = Database::connection()->lastInsertId();

    // Log activity
    Database::execute(
        "INSERT INTO activity_logs
            (user_id, action, module_name, reference_code, ip_address, new_value)
         VALUES
            (:user_id, :action, :module_name, :reference_code, :ip_address, :new_value)",
        [
            'user_id'        => $user['user_id'],
            'action'         => 'create',
            'module_name'    => 'passenger_accounts',
            'reference_code' => 'PAX-' . $passengerId,
            'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'new_value'      => json_encode([
                'passenger_id' => $passengerId,
                'fullname' => $fullname,
                'mobile_number' => $mobileNumber,
                'email' => $email,
                'gender' => $gender,
                'region_code' => $regionCode,
                'province_code' => $provinceCode,
                'city_municipality_code' => $cityCode,
                'barangay_code' => $barangayCode,
            ])
        ]
    );

    echo json_encode([
        'success'      => true,
        'message'      => 'Passenger added successfully.',
        'passenger_id' => $passengerId,
        'passenger_code' => $passengerCode,
        'fullname'     => $fullname,
        'mobile_number' => $mobileNumber,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to add passenger: ' . $e->getMessage()]);
}

/**
 * Handle GET requests - fetch passengers
 */
function handleGet($user) {
    try {
        // Get search parameter
        $search = $_GET['search'] ?? '';
        
        // Get passenger_id parameter for single passenger fetch
        $passengerId = $_GET['passenger_id'] ?? null;
        
        // If passenger_id is provided, fetch single passenger
        if ($passengerId) {
            $sql = "SELECT p.*, 
                           r.region_name,
                           pr.province_name,
                           c.city_municipality_name,
                           b.barangay_name,
                           CONCAT(e.first_name, ' ', e.last_name) as created_by_name
                    FROM passenger_accounts p
                    LEFT JOIN user_accounts u ON p.created_by = u.user_id
                    LEFT JOIN employees e ON u.emp_id = e.emp_id
                    LEFT JOIN psgc_regions r ON p.region_code = r.region_code
                    LEFT JOIN psgc_provinces pr ON p.province_code = pr.province_code
                    LEFT JOIN psgc_cities_municipalities c ON p.city_municipality_code = c.city_municipality_code
                    LEFT JOIN psgc_barangays b ON p.barangay_code = b.barangay_code
                    WHERE p.passenger_id = :passenger_id AND p.deleted_at IS NULL";
            
            $passenger = Database::fetch($sql, ['passenger_id' => $passengerId]);
            
            if ($passenger) {
                echo json_encode(['success' => true, 'data' => $passenger]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Passenger not found']);
            }
            return;
        }
        
        // Fetch passengers from passenger_accounts table with PSGC names
        $sql = "SELECT p.passenger_id, p.fullname, p.mobile_number, p.email, 
                       p.region_code, p.province_code, p.city_municipality_code, p.barangay_code,
                       p.street_address, p.landmark, p.zip_code, p.gender, p.birth_date, p.notes,
                       p.created_at, p.updated_at, p.created_by,
                       r.region_name,
                       pr.province_name,
                       c.city_municipality_name,
                       b.barangay_name,
                       CONCAT(e.first_name, ' ', e.last_name) as created_by_name
                FROM passenger_accounts p
                LEFT JOIN user_accounts u ON p.created_by = u.user_id
                LEFT JOIN employees e ON u.emp_id = e.emp_id
                LEFT JOIN psgc_regions r ON p.region_code = r.region_code
                LEFT JOIN psgc_provinces pr ON p.province_code = pr.province_code
                LEFT JOIN psgc_cities_municipalities c ON p.city_municipality_code = c.city_municipality_code
                LEFT JOIN psgc_barangays b ON p.barangay_code = b.barangay_code
                WHERE p.deleted_at IS NULL";
        
        $params = [];
        
        // Add search filter with word shuffling support
        if ($search) {
            // Simple LIKE search for better compatibility - use unique parameter names
            $sql .= " AND (p.fullname LIKE :search_fullname OR p.mobile_number LIKE :search_mobile OR p.email LIKE :search_email OR p.street_address LIKE :search_street OR pr.province_name LIKE :search_province OR c.city_municipality_name LIKE :search_city OR b.barangay_name LIKE :search_barangay)";
            $searchParam = '%' . $search . '%';
            $params['search_fullname'] = $searchParam;
            $params['search_mobile'] = $searchParam;
            $params['search_email'] = $searchParam;
            $params['search_street'] = $searchParam;
            $params['search_province'] = $searchParam;
            $params['search_city'] = $searchParam;
            $params['search_barangay'] = $searchParam;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $passengers = Database::fetchAll($sql, $params);
        
        echo json_encode([
            'success' => true,
            'data' => $passengers
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch passengers: ' . $e->getMessage()]);
    }
}

/**
 * Handle PUT requests - update passenger
 */
function handlePut($user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $passengerId = trim($input['passenger_id'] ?? '');
    $fullname = trim($input['fullname'] ?? '');
    $mobileNumber = trim($input['mobile_number'] ?? '');
    $email = trim($input['email'] ?? '');
    $gender = trim($input['gender'] ?? '');
    $birthDate = trim($input['birth_date'] ?? '');
    $regionCode = trim($input['region_code'] ?? '');
    $provinceCode = trim($input['province_code'] ?? '');
    $cityCode = trim($input['city_municipality_code'] ?? '');
    $barangayCode = trim($input['barangay_code'] ?? '');
    $streetAddress = trim($input['street_address'] ?? '');
    $notes = trim($input['notes'] ?? '');
    
    // Validate
    if (!$passengerId) { echo json_encode(['success' => false, 'error' => 'Passenger ID is required.']); exit; }
    if (!$fullname) { echo json_encode(['success' => false, 'error' => 'Fullname is required.']); exit; }
    // Mobile number is optional, only validate if provided
    if ($mobileNumber && !preg_match('/^09[0-9]{9}$/', $mobileNumber)) {
        echo json_encode(['success' => false, 'error' => 'Mobile number must be 11 digits starting with 09.']); exit;
    }
    
    try {
        // Check if passenger exists
        $existing = Database::fetch(
            "SELECT passenger_id FROM passenger_accounts WHERE passenger_id = :passenger_id AND deleted_at IS NULL",
            ['passenger_id' => $passengerId]
        );
        
        if (!$existing) {
            echo json_encode(['success' => false, 'error' => 'Passenger not found.']);
            exit;
        }
        
        // Check if mobile number is used by another passenger
        $mobileCheck = Database::fetch(
            "SELECT passenger_id FROM passenger_accounts WHERE mobile_number = :mobile AND passenger_id != :passenger_id",
            ['mobile' => $mobileNumber, 'passenger_id' => $passengerId]
        );
        
        if ($mobileCheck) {
            echo json_encode(['success' => false, 'error' => 'Mobile number already used by another passenger.']);
            exit;
        }
        
        // Get old passenger data for activity log
        $oldData = Database::fetch(
            "SELECT * FROM passenger_accounts WHERE passenger_id = :passenger_id",
            ['passenger_id' => $passengerId]
        );
        
        // Update passenger
        Database::execute(
            "UPDATE passenger_accounts
             SET fullname = :fullname,
                 mobile_number = :mobile,
                 email = :email,
                 gender = :gender,
                 birth_date = :birth_date,
                 region_code = :region_code,
                 province_code = :province_code,
                 city_municipality_code = :city_code,
                 barangay_code = :barangay_code,
                 street_address = :street_address,
                 notes = :notes,
                 updated_at = NOW()
             WHERE passenger_id = :passenger_id",
            [
                'fullname'      => $fullname,
                'mobile'        => $mobileNumber,
                'email'         => $email ?: null,
                'gender'        => $gender ?: null,
                'birth_date'    => $birthDate ?: null,
                'region_code'   => $regionCode ?: null,
                'province_code' => $provinceCode ?: null,
                'city_code'     => $cityCode ?: null,
                'barangay_code' => $barangayCode ?: null,
                'street_address'=> $streetAddress ?: null,
                'notes'         => $notes ?: null,
                'passenger_id'  => $passengerId,
            ]
        );
        
        // Log activity
        Database::execute(
            "INSERT INTO activity_logs
                (user_id, action, module_name, reference_code, ip_address, old_value, new_value)
             VALUES
                (:user_id, :action, :module_name, :reference_code, :ip_address, :old_value, :new_value)",
            [
                'user_id'        => $user['user_id'],
                'action'         => 'update',
                'module_name'    => 'passenger_accounts',
                'reference_code' => 'PAX-' . $passengerId,
                'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'old_value'      => json_encode($oldData),
                'new_value'      => json_encode([
                    'passenger_id' => $passengerId,
                    'fullname' => $fullname,
                    'mobile_number' => $mobileNumber,
                    'email' => $email,
                    'gender' => $gender,
                    'region_code' => $regionCode,
                    'province_code' => $provinceCode,
                    'city_municipality_code' => $cityCode,
                    'barangay_code' => $barangayCode,
                ])
            ]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Passenger updated successfully.',
            'passenger_id' => $passengerId,
            'fullname' => $fullname,
            'mobile_number' => $mobileNumber,
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to update passenger: ' . $e->getMessage()]);
    }
}
