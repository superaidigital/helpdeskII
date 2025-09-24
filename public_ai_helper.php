<?php
header('Content-Type: application/json');
require_once 'includes/functions.php'; // This includes db.php and starts session

// Gemini API Configuration
define('GEMINI_API_KEY', 'AIzaSyCEHI88GtEHBHEE2C1vjrOyKKVv-1kl5W4'); 
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . GEMINI_API_KEY);

function callGeminiAPI($prompt) {
    if (GEMINI_API_KEY === '' || strpos(GEMINI_API_KEY, 'YOUR_GEMINI_API_KEY') !== false) {
        return ['success' => false, 'data' => "AI Service is not configured."];
    }
    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    $ch = curl_init(GEMINI_API_URL);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($data), CURLOPT_SSL_VERIFYPEER => true]);
    $response_json = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) return ['success' => false, 'data' => 'cURL Error: ' . $curl_error];
    $response_data = json_decode($response_json, true);
    if (isset($response_data['error'])) return ['success' => false, 'data' => 'API Error: ' . ($response_data['error']['message'] ?? 'Unknown API error')];
    $text = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    return $text ? ['success' => true, 'data' => $text] : ['success' => false, 'data' => 'Invalid response from AI.'];
}

// Get input from the form
$input = json_decode(file_get_contents('php://input'), true);
$title = $input['title'] ?? '';
$description = $input['description'] ?? '';

if (empty($title)) {
    echo json_encode(['success' => false, 'suggestion' => 'กรุณากรอกหัวข้อปัญหา']);
    exit();
}

// 1. Search the Knowledge Base first
$search_query = "%" . $title . "%";
$stmt = $conn->prepare("SELECT question, answer FROM knowledge_base WHERE question LIKE ? LIMIT 1");
$stmt->bind_param("s", $search_query);
$stmt->execute();
$result = $stmt->get_result();
$suggestion = '';

if ($result->num_rows > 0) {
    $kb_item = $result->fetch_assoc();
    $suggestion = "💡 **จากฐานข้อมูลของเรา พบวิธีแก้ปัญหาที่ใกล้เคียง:**\n\n"
                . "**ปัญหา:** " . htmlspecialchars($kb_item['question']) . "\n"
                . "**แนวทางแก้ไข:**\n" . htmlspecialchars($kb_item['answer']);
    $response = ['success' => true, 'suggestion' => $suggestion];
} else {
    // 2. If not found, call Gemini API
    $prompt = "ผู้ใช้กำลังแจ้งปัญหาผ่านระบบ IT Helpdesk โปรดให้คำแนะนำวิธีแก้ไขเบื้องต้นที่เข้าใจง่ายและทำตามได้สำหรับผู้ใช้ทั่วไป โดยตอบเป็นภาษาไทย\n\n"
            . "**หัวข้อปัญหา:** " . htmlspecialchars($title) . "\n"
            . "**รายละเอียด:** " . htmlspecialchars($description) . "\n\n"
            . "**คำแนะนำเบื้องต้น:**";
    
    $ai_result = callGeminiAPI($prompt);

    if ($ai_result['success']) {
        $suggestion = "🤖 **AI ขอแนะนำวิธีแก้ไขเบื้องต้น:**\n\n" . $ai_result['data'];
        $response = ['success' => true, 'suggestion' => $suggestion];
    } else {
        $response = ['success' => false, 'suggestion' => 'ขออภัย, ไม่สามารถติดต่อผู้ช่วย AI ได้ในขณะนี้ (' . $ai_result['data'] . ')'];
    }
}

$stmt->close();
// Log the interaction to the database
$interaction_id = null;
if ($response['success']) {
    $stmt_log = $conn->prepare(
        "INSERT INTO ai_interactions (question_title, question_description, ai_suggestion, video_id) VALUES (?, ?, ?, NULL)" // video_id is now NULL
    );
    $stmt_log->bind_param("sss", $title, $description, $suggestion);
    $stmt_log->execute();
    $interaction_id = $conn->insert_id;
    $stmt_log->close();
}

$response['interactionId'] = $interaction_id;

$conn->close();
echo json_encode($response);
exit();