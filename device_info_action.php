<?php
require_once 'includes/functions.php';
check_auth(['it', 'admin']); // Only IT and Admins can perform this action

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();

    $issue_id = isset($_POST['issue_id']) ? (int)$_POST['issue_id'] : 0;
    if ($issue_id <= 0) {
        redirect_with_message('it_dashboard.php', 'error', 'Invalid Issue ID.');
    }

    // Sanitize and get data from the form
    $ownership_type = $_POST['ownership_type'] ?? '';
    $device_category = $_POST['device_category'] ?? '';
    $asset_code = ($ownership_type === 'office') ? trim($_POST['asset_code'] ?? '') : null;
    $brand = trim($_POST['brand'] ?? '');
    $specs_details = trim($_POST['specs_details'] ?? '');
    $current_user_id = $_SESSION['user_id'];
    $device_id = isset($_POST['device_id']) ? (int)$_POST['device_id'] : 0;

    if (empty($ownership_type) || empty($device_category)) {
        redirect_with_message("issue_view.php?id=$issue_id", 'error', 'กรุณาระบุประเภทและหมวดหมู่อุปกรณ์');
    }

    if ($device_id > 0) {
        // Update existing device record
        $stmt = $conn->prepare(
            "UPDATE issue_devices SET ownership_type = ?, device_category = ?, asset_code = ?, brand = ?, specs_details = ?, updated_by = ? WHERE id = ?"
        );
        $stmt->bind_param("sssssii", $ownership_type, $device_category, $asset_code, $brand, $specs_details, $current_user_id, $device_id);
    } else {
        // Insert new device record
        $stmt = $conn->prepare(
            "INSERT INTO issue_devices (issue_id, ownership_type, device_category, asset_code, brand, specs_details, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isssssi", $issue_id, $ownership_type, $device_category, $asset_code, $brand, $specs_details, $current_user_id);
    }

    if ($stmt->execute()) {
        // If it was a new insert, update the issues table with the new device ID
        if ($device_id === 0) {
            $new_device_id = $conn->insert_id;
            $update_issue_stmt = $conn->prepare("UPDATE issues SET device_id = ? WHERE id = ?");
            $update_issue_stmt->bind_param("ii", $new_device_id, $issue_id);
            $update_issue_stmt->execute();
            $update_issue_stmt->close();
        }
        redirect_with_message("issue_view.php?id=$issue_id", 'success', 'บันทึกข้อมูลอุปกรณ์เรียบร้อยแล้ว');
    } else {
        redirect_with_message("issue_view.php?id=$issue_id", 'error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูลอุปกรณ์');
    }
    
    $stmt->close();
    $conn->close();

} else {
    header("Location: it_dashboard.php");
    exit();
}