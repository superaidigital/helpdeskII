<?php
$page_title = "รายงานวิเคราะห์ภาพรวมโดย AI";
require_once 'includes/functions.php';
check_auth(['admin']);

// Gemini API Configuration
define('GEMINI_API_KEY', 'AIzaSyCEHI88GtEHBHEE2C1vjrOyKKVv-1kl5W4'); 
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . GEMINI_API_KEY);

function callGeminiAPI($prompt) {
    if (GEMINI_API_KEY === '' || strpos(GEMINI_API_KEY, 'YOUR_GEMINI_API_KEY') !== false) {
        return ['success' => false, 'data' => "ข้อผิดพลาด: กรุณาตั้งค่า GEMINI_API_KEY ในไฟล์ " . basename(__FILE__) . " ก่อนใช้งาน"];
    }
    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    $ch = curl_init(GEMINI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response_json = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) return ['success' => false, 'data' => 'cURL Error: ' . $curl_error];
    $response_data = json_decode($response_json, true);
    if ($http_code !== 200 || isset($response_data['error'])) {
        return ['success' => false, 'data' => 'API Error: ' . ($response_data['error']['message'] ?? 'Unknown API error')];
    }
    $text = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    return $text ? ['success' => true, 'data' => $text] : ['success' => false, 'data' => 'Invalid response from AI.'];
}

function parseMarkdownToHtml($markdown) {
    $html = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');
    $html = preg_replace('/^## (.*$)/m', '<h3 class="text-xl font-bold text-gray-800 mt-6 mb-2">$1</h3>', $html);
    $html = preg_replace('/\*\*(.*?)\*\*/s', '<strong class="font-semibold text-gray-900">$1</strong>', $html);
    $html = preg_replace_callback('/((?:^\s*[-*] .*(?:\n|$))+)/m', function($matches) {
        $items = preg_replace('/^\s*[-*] (.*)/m', '<li class="mb-1">$1</li>', $matches[0]);
        return '<ul class="list-disc list-inside space-y-1 pl-4 mt-2 mb-4">' . $items . '</ul>';
    }, $html);
    $html = nl2br($html);
    $html = preg_replace('/<ul><br\s*\/?>/i', '<ul>', $html);
    $html = preg_replace('/<\/li><br\s*\/?>/i', '</li>', $html);
    return $html;
}

// Function to fetch stats for a given period
function getStatsForPeriod($conn, $start, $end) {
    $stats = [];
    $base_condition_issues = "DATE(i.created_at) BETWEEN ? AND ?";
    $base_condition_ai = "DATE(created_at) BETWEEN ? AND ?";
    
    // Overall Issues Stats
    $stmt = $conn->prepare("SELECT COUNT(id) as total_issues, COUNT(CASE WHEN status = 'done' THEN 1 END) as total_completed, AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours, AVG(satisfaction_rating) as avg_rating FROM issues i WHERE $base_condition_issues");
    $stmt->bind_param("ss", $start, $end); $stmt->execute();
    $stats['overall'] = $stmt->get_result()->fetch_assoc(); $stmt->close();
    
    // AI Interactions Stats
    $stmt_ai = $conn->prepare("SELECT COUNT(id) as total_interactions, SUM(CASE WHEN ticket_created = 0 THEN 1 ELSE 0 END) as solved_by_ai FROM ai_interactions WHERE $base_condition_ai");
    $stmt_ai->bind_param("ss", $start, $end); $stmt_ai->execute();
    $stats['ai'] = $stmt_ai->get_result()->fetch_assoc(); $stmt_ai->close();

    // Calculate Ticket Deflection Rate
    $total_interactions = $stats['ai']['total_interactions'] ?? 0;
    $solved_by_ai = $stats['ai']['solved_by_ai'] ?? 0;
    $stats['ai']['deflection_rate'] = ($total_interactions > 0) ? ($solved_by_ai / $total_interactions) * 100 : 0;
    
    // Add other detailed data fetching for the prompt
    $stmt_problems = $conn->prepare("SELECT title, COUNT(id) as total FROM issues i WHERE $base_condition_issues GROUP BY title ORDER BY total DESC LIMIT 5");
    $stmt_problems->bind_param("ss", $start, $end); $stmt_problems->execute();
    $stats['top_problems'] = $stmt_problems->get_result()->fetch_all(MYSQLI_ASSOC); $stmt_problems->close();

    $stmt_depts = $conn->prepare("SELECT reporter_department, COUNT(id) as total FROM issues i WHERE $base_condition_issues AND reporter_department IS NOT NULL AND reporter_department != '' GROUP BY reporter_department ORDER BY total DESC LIMIT 5");
    $stmt_depts->bind_param("ss", $start, $end); $stmt_depts->execute();
    $stats['top_departments'] = $stmt_depts->get_result()->fetch_all(MYSQLI_ASSOC); $stmt_depts->close();
    
    $stmt_staff = $conn->prepare("SELECT u.fullname, COUNT(i.id) as total, AVG(TIMESTAMPDIFF(MINUTE, i.created_at, i.completed_at)) as avg_min, AVG(i.satisfaction_rating) as avg_rating FROM issues i JOIN users u ON i.assigned_to = u.id WHERE u.role IN ('it', 'admin') AND DATE(i.created_at) BETWEEN ? AND ? GROUP BY u.id ORDER BY total DESC");
    $stmt_staff->bind_param("ss", $start, $end); $stmt_staff->execute();
    $stats['staff_performance'] = $stmt_staff->get_result()->fetch_all(MYSQLI_ASSOC); $stmt_staff->close();

    return $stats;
}

// === Handle Asynchronous API Request ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $start_date = $input['start_date'] ?? null; $end_date = $input['end_date'] ?? null;
    if (!$start_date || !$end_date) { echo json_encode(['success' => false, 'html' => '<p class="text-red-500">Invalid date range.</p>']); exit; }

    // Re-fetch data for the prompt
    $current_stats = getStatsForPeriod($conn, $start_date, $end_date);
    $date_diff_days = (new DateTime($start_date))->diff(new DateTime($end_date))->days;
    $prev_end_date = date('Y-m-d', strtotime('-1 day', strtotime($start_date)));
    $prev_start_date = date('Y-m-d', strtotime("-$date_diff_days days", strtotime($prev_end_date)));
    $prev_stats = getStatsForPeriod($conn, $prev_start_date, $prev_end_date);

    $data_for_prompt = "ข้อมูลสรุปจากระบบ IT Helpdesk\n";
    $data_for_prompt .= "ช่วงเวลาปัจจุบัน: $start_date ถึง $end_date\n";
    $data_for_prompt .= "ช่วงเวลาก่อนหน้าสำหรับเปรียบเทียบ: $prev_start_date ถึง $prev_end_date\n\n";

    $data_for_prompt .= "## ตารางเปรียบเทียบข้อมูลสรุป\n";
    $data_for_prompt .= "| ตัวชี้วัด | ช่วงเวลาปัจจุบัน | ช่วงเวลาก่อนหน้า |\n";
    $data_for_prompt .= "|---|---|---|\n";
    $data_for_prompt .= "| จำนวนเรื่องทั้งหมด | " . ($current_stats['overall']['total_issues'] ?? 0) . " | " . ($prev_stats['overall']['total_issues'] ?? 0) . " |\n";
    $data_for_prompt .= "| แก้ไขเสร็จสิ้น | " . ($current_stats['overall']['total_completed'] ?? 0) . " | " . ($prev_stats['overall']['total_completed'] ?? 0) . " |\n";
    $data_for_prompt .= "| เวลาแก้ไขเฉลี่ย (ชม.) | " . round($current_stats['overall']['avg_hours'] ?? 0, 1) . " | " . round($prev_stats['overall']['avg_hours'] ?? 0, 1) . " |\n";
    $data_for_prompt .= "| คะแนนความพึงพอใจ | " . round($current_stats['overall']['avg_rating'] ?? 0, 2) . "/5 | " . round($prev_stats['overall']['avg_rating'] ?? 0, 2) . "/5 |\n\n";

    $data_for_prompt .= "## ประสิทธิภาพของฟีเจอร์ AI ช่วยแนะนำ (ช่วงปัจจุบัน)\n";
    $data_for_prompt .= "- มีการใช้งาน AI ทั้งหมด: " . ($current_stats['ai']['total_interactions'] ?? 0) . " ครั้ง\n";
    $data_for_prompt .= "- จำนวนเรื่องที่ AI ช่วยแก้ไขได้ (ไม่ต้องสร้าง Ticket): " . ($current_stats['ai']['solved_by_ai'] ?? 0) . " ครั้ง\n";
    $data_for_prompt .= "- อัตราการลดจำนวน Ticket (Deflection Rate): " . number_format($current_stats['ai']['deflection_rate'] ?? 0, 2) . "%\n\n";

    $prompt = "ในฐานะ **นักวิเคราะห์นโยบายและแผน** ขององค์การบริหารส่วนจังหวัดศรีสะเกษ \n"
            . "โปรดจัดทำ **บันทึกข้อความ** เพื่อรายงานผลการวิเคราะห์ข้อมูลจากระบบแจ้งปัญหาและให้คำปรึกษาด้าน IT (IT Helpdesk) \n"
            . "โดยใช้ข้อมูลสรุปต่อไปนี้:\n\n"
            . $data_for_prompt
            . "\n\n**คำสั่ง:**\n"
            . "กรุณาจัดทำรายงานตามโครงสร้างของเอกสารราชการ โดยใช้ภาษาที่เป็นทางการและมีหัวข้อดังต่อไปนี้:\n\n"
            . "**เรื่อง:** รายงานผลการวิเคราะห์ข้อมูลและข้อเสนอแนะเชิงนโยบาย จากระบบ IT Helpdesk\n\n"
            . "**เรียน:** ผู้บริหารเทคโนโลยีสารสนเทศระดับสูง (CIO)\n\n"
            . "1. **บทสรุปสำหรับผู้บริหาร:** สรุปภาพรวมที่สำคัญที่สุด, แนวโน้มที่น่าจับตามอง, และข้อเสนอแนะที่เร่งด่วนที่สุดใน 3-4 ประโยค\n\n"
            . "2. **ผลการวิเคราะห์ข้อมูลเชิงลึก:** วิเคราะห์เปรียบเทียบข้อมูลระหว่างสองช่วงเวลาอย่างละเอียดในแต่ละด้าน (ปริมาณงาน, ประสิทธิภาพ, ภาระงานบุคลากร, ประเภทปัญหา)\n\n"
            . "3. **วิเคราะห์ประสิทธิผลของฟีเจอร์ AI:** จากข้อมูล 'ประสิทธิภาพของฟีเจอร์ AI' ฟีเจอร์นี้ประสบความสำเร็จมากน้อยเพียงใด? อัตราการลดจำนวน Ticket อยู่ในเกณฑ์ที่น่าพอใจหรือไม่? พร้อมให้ข้อเสนอแนะเพื่อเพิ่มประสิทธิภาพของฟีเจอร์นี้\n\n"
            . "4. **ข้อเสนอแนะเชิงนโยบายเพื่อการพัฒนา:** แบ่งเป็นข้อเสนอแนะระยะสั้น (1-3 เดือน) และระยะยาว (6-12 เดือน) ที่ชัดเจนและนำไปปฏิบัติได้จริง\n\n"
            . "**จึงเรียนมาเพื่อโปรดพิจารณา**\n\n"
            . "โปรดใช้ Markdown ในการจัดรูปแบบคำตอบให้สวยงาม";

    $ai_result = callGeminiAPI($prompt);
    
    if ($ai_result['success']) {
        echo json_encode(['success' => true, 'html' => parseMarkdownToHtml($ai_result['data']), 'markdown' => $ai_result['data']]);
    } else {
        echo json_encode(['success' => false, 'html' => '<p class="text-red-500 font-semibold">เกิดข้อผิดพลาด: ' . htmlspecialchars($ai_result['data']) . '</p>', 'markdown' => '']);
    }
    $conn->close();
    exit();
}

// === Initial Page Load (GET Request) ===
$page_title = "รายงานวิเคราะห์ภาพรวมโดย AI";
require_once 'includes/header.php';

$end_date = $_GET['end_date'] ?? date('Y-m-d');
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days', strtotime($end_date)));
$current_stats = getStatsForPeriod($conn, $start_date, $end_date);
?>
<style>
.dots-container .dot { display: inline-block; width: 12px; height: 12px; background-color: #a5b4fc; border-radius: 50%; margin: 0 4px; animation: dot-bounce 1.4s infinite ease-in-out both; }
.animate-dot1 { animation-delay: -0.32s; } .animate-dot2 { animation-delay: -0.16s; }
@keyframes dot-bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1.0); } }
</style>

<div class="space-y-6" x-data="aiAnalytics('<?php echo htmlspecialchars($start_date); ?>', '<?php echo htmlspecialchars($end_date); ?>')">
    <div class="bg-white p-4 rounded-lg shadow-md">
        <form @submit.prevent="runAnalysis" class="flex flex-col sm:flex-row items-center gap-4">
            <div>
                <label for="start_date" class="text-sm font-medium text-gray-700">วันที่เริ่มต้น:</label>
                <input type="date" id="start_date" x-model="startDate" class="mt-1 block border border-gray-300 rounded-md shadow-sm py-2 px-3">
            </div>
            <div>
                <label for="end_date" class="text-sm font-medium text-gray-700">วันที่สิ้นสุด:</label>
                <input type="date" id="end_date" x-model="endDate" class="mt-1 block border border-gray-300 rounded-md shadow-sm py-2 px-3">
            </div>
            <div>
                <button type="submit" :disabled="isLoading" class="w-full sm:w-auto mt-6 px-4 py-2 bg-indigo-600 text-white font-semibold rounded-md hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                    <span x-show="!isLoading"><i class="fa-solid fa-brain mr-2"></i>วิเคราะห์ข้อมูล</span>
                    <span x-show="isLoading" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-2"></i>กำลังวิเคราะห์...</span>
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-bold text-gray-800 mb-4">ประสิทธิภาพ AI ช่วยแนะนำ</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
            <div>
                <p class="text-sm font-medium text-gray-500">จำนวนการใช้งาน AI</p>
                <p class="text-3xl font-bold text-indigo-600 mt-1"><?php echo number_format($current_stats['ai']['total_interactions'] ?? 0); ?></p>
                <p class="text-xs text-gray-500">ครั้ง</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">เรื่องที่ AI ช่วยแก้ปัญหาได้</p>
                <p class="text-3xl font-bold text-green-600 mt-1"><?php echo number_format($current_stats['ai']['solved_by_ai'] ?? 0); ?></p>
                <p class="text-xs text-gray-500">(ผู้ใช้ไม่ต้องสร้าง Ticket)</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">อัตราการลดจำนวน Ticket</p>
                <p class="text-3xl font-bold text-blue-600 mt-1"><?php echo number_format($current_stats['ai']['deflection_rate'] ?? 0, 1); ?>%</p>
                <p class="text-xs text-gray-500">(Ticket Deflection Rate)</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white p-6 rounded-lg shadow-md min-h-[20rem] flex flex-col">
        <div class="flex items-start justify-between gap-4 border-b pb-4 mb-4">
            <div class="flex items-center gap-4">
                <i class="fa-solid fa-robot text-3xl text-indigo-500 flex-shrink-0"></i>
                <div>
                    <h3 class="text-xl font-bold text-gray-800">บทวิเคราะห์และข้อเสนอแนะโดย AI</h3>
                    <p class="text-sm text-gray-500" x-text="`วิเคราะห์จากข้อมูลระหว่างวันที่ ${startDate} ถึง ${endDate}`"></p>
                </div>
            </div>
            <div x-show="!isLoading && rawMarkdown" x-transition>
                 <button @click="copyToClipboard" class="px-3 py-2 bg-gray-100 text-gray-600 rounded-md hover:bg-gray-200 text-sm font-semibold transition-colors">
                    <span x-show="!copied"><i class="fa-regular fa-copy mr-2"></i>คัดลอก</span>
                    <span x-show="copied" style="display: none;" class="text-green-600"><i class="fa-solid fa-check mr-2"></i>คัดลอกแล้ว!</span>
                </button>
            </div>
        </div>
        
        <div class="flex-grow flex items-center justify-center">
            <div x-show="isLoading" x-transition.opacity class="text-center">
                 <div class="dots-container">
                    <span class="dot animate-dot1"></span>
                    <span class="dot animate-dot2"></span>
                    <span class="dot animate-dot3"></span>
                </div>
                <p class="mt-4 text-lg font-semibold text-gray-600">AI กำลังประมวลผลข้อมูล...</p>
                <p class="text-sm text-gray-500">กรุณารอสักครู่ ระบบกำลังวิเคราะห์ข้อมูลเชิงลึกสำหรับคุณ</p>
            </div>
            <div x-show="!isLoading" class="prose max-w-none text-gray-700 w-full" x-html="aiResponseHtml"></div>
        </div>
    </div>
</div>

<script>
function aiAnalytics(initialStartDate, initialEndDate) {
    return {
        isLoading: false,
        aiResponseHtml: '<div class="text-center text-gray-500"><p>กรุณากดปุ่ม \'วิเคราะห์ข้อมูล\' เพื่อให้ AI เริ่มทำงาน</p></div>',
        rawMarkdown: '',
        copied: false,
        startDate: initialStartDate,
        endDate: initialEndDate,
        runAnalysis() {
            this.isLoading = true;
            this.aiResponseHtml = '';
            this.rawMarkdown = '';
            this.copied = false;

            fetch('admin_ai_analytics.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ start_date: this.startDate, end_date: this.endDate })
            })
            .then(res => { if (!res.ok) { throw new Error('Network response error.'); } return res.json(); })
            .then(data => { 
                this.aiResponseHtml = data.html;
                this.rawMarkdown = data.markdown;
            })
            .catch(err => { this.aiResponseHtml = '<p class="text-center text-red-500 font-semibold">ไม่สามารถเชื่อมต่อเพื่อวิเคราะห์ข้อมูลได้ กรุณาลองใหม่อีกครั้ง</p>'; })
            .finally(() => { this.isLoading = false; });
        },
        copyToClipboard() {
            if (!this.rawMarkdown) return;
            navigator.clipboard.writeText(this.rawMarkdown).then(() => {
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2000);
            }).catch(err => {
                console.error('Failed to copy text: ', err);
                alert('ไม่สามารถคัดลอกได้');
            });
        }
    }
}
</script>

<?php 
$conn->close();
require_once 'includes/footer.php'; 
?>