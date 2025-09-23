<?php
$page_title = "รายงานวิเคราะห์ภาพรวมโดย AI";
require_once 'includes/functions.php';
check_auth(['admin']);
require_once 'includes/header.php';

// --- Date Filtering Logic ---
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$base_condition = "DATE(created_at) BETWEEN ? AND ?";

// Fetch data for AI analysis (reusing queries from other report pages)
// 1. Top 5 Most Frequent Problems
$top_problems_stmt = $conn->prepare("SELECT title, COUNT(id) as total FROM issues WHERE $base_condition GROUP BY title ORDER BY total DESC, title ASC LIMIT 5");
$top_problems_stmt->bind_param("ss", $start_date, $end_date);
$top_problems_stmt->execute();
$top_problems_q = $top_problems_stmt->get_result();
$top_problems_data = $top_problems_q->fetch_all(MYSQLI_ASSOC);
$top_problems_stmt->close();

// 2. Top 5 Busiest Departments
$top_departments_stmt = $conn->prepare("SELECT reporter_department, COUNT(id) as total FROM issues WHERE $base_condition AND reporter_department IS NOT NULL AND reporter_department != '' GROUP BY reporter_department ORDER BY total DESC, reporter_department ASC LIMIT 5");
$top_departments_stmt->bind_param("ss", $start_date, $end_date);
$top_departments_stmt->execute();
$top_departments_q = $top_departments_stmt->get_result();
$top_departments_data = $top_departments_q->fetch_all(MYSQLI_ASSOC);
$top_departments_stmt->close();

// 3. Overall Stats
$stmt = $conn->prepare("SELECT COUNT(id) as total, AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours FROM issues WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$overall_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// --- Placeholder for AI Analysis ---
// In a real application, you would send the fetched data to a generative AI API.
// For this demonstration, we will simulate the AI's response based on the data.
$ai_summary = "จากการวิเคราะห์ข้อมูลระหว่างวันที่ " . formatDate($start_date) . " ถึง " . formatDate($end_date) . ", พบว่ามีประเด็นที่น่าสนใจดังนี้:\n\n";
$ai_summary .= "**ภาพรวม:**\n";
$ai_summary .= "- มีเรื่องแจ้งเข้ามาทั้งหมดจำนวน " . ($overall_stats['total'] ?? 0) . " เรื่อง\n";
$ai_summary .= "- เวลาเฉลี่ยในการแก้ไขปัญหาอยู่ที่ประมาณ " . round($overall_stats['avg_hours'] ?? 0, 1) . " ชั่วโมงต่อเรื่อง\n\n";

$ai_summary .= "**ปัญหาที่พบบ่อย:**\n";
if (!empty($top_problems_data)) {
    $ai_summary .= "ปัญหาที่ถูกแจ้งบ่อยที่สุดคือ \"" . htmlspecialchars($top_problems_data[0]['title']) . "\" ซึ่งอาจบ่งชี้ถึงปัญหาเชิงระบบที่ควรได้รับการตรวจสอบแก้ไขในระยะยาว การจัดทำคู่มือหรือ Knowledge Base สำหรับปัญหานี้จะช่วยลดจำนวนการแจ้งเรื่องซ้ำซ้อนได้\n\n";
} else {
    $ai_summary .= "ยังไม่มีข้อมูลปัญหาที่พบบ่อยเพียงพอที่จะสรุปแนวโน้มได้\n\n";
}

$ai_summary .= "**หน่วยงานที่ใช้งานสูงสุด:**\n";
if (!empty($top_departments_data)) {
    $ai_summary .= "หน่วยงานที่แจ้งปัญหาเข้ามามากที่สุดคือ \"" . htmlspecialchars($top_departments_data[0]['reporter_department']) . "\" ควรมีการจัดอบรมการใช้งานระบบพื้นฐานให้กับหน่วยงานนี้ เพื่อเพิ่มความสามารถในการแก้ปัญหาเบื้องต้นด้วยตนเอง\n\n";
} else {
    $ai_summary .= "ข้อมูลหน่วยงานที่แจ้งปัญหายังไม่เพียงพอต่อการวิเคราะห์\n\n";
}

$ai_summary .= "**ข้อเสนอแนะ:**\n";
$ai_summary .= "ควรให้ความสำคัญกับการแก้ไขปัญหาที่พบบ่อยเป็นอันดับแรก และพิจารณาจัดทำสื่อการเรียนรู้เพื่อลดภาระงานของเจ้าหน้าที่ในอนาคต";

?>
<div class="space-y-6">
    <div class="bg-white p-4 rounded-lg shadow-md">
        <form method="GET" action="admin_ai_analytics.php" class="flex flex-col sm:flex-row items-center gap-4">
            <div>
                <label for="start_date" class="text-sm font-medium text-gray-700">วันที่เริ่มต้น:</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="mt-1 block border border-gray-300 rounded-md shadow-sm py-2 px-3">
            </div>
            <div>
                <label for="end_date" class="text-sm font-medium text-gray-700">วันที่สิ้นสุด:</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="mt-1 block border border-gray-300 rounded-md shadow-sm py-2 px-3">
            </div>
            <div>
                <button type="submit" class="w-full sm:w-auto mt-6 px-4 py-2 bg-indigo-600 text-white font-semibold rounded-md hover:bg-indigo-700">
                    <i class="fa-solid fa-brain mr-2"></i>วิเคราะห์ข้อมูล
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex items-center gap-4 border-b pb-4 mb-4">
            <i class="fa-solid fa-robot text-3xl text-indigo-500"></i>
            <div>
                <h3 class="text-xl font-bold text-gray-800">บทสรุปและข้อเสนอแนะโดย AI</h3>
                <p class="text-sm text-gray-500">สรุปจากข้อมูลระหว่างวันที่ <?php echo htmlspecialchars($start_date); ?> ถึง <?php echo htmlspecialchars($end_date); ?></p>
            </div>
        </div>
        <div class="prose max-w-none text-gray-700">
            <?php echo nl2br(htmlspecialchars($ai_summary)); ?>
        </div>
    </div>
</div>

<?php 
$conn->close();
require_once 'includes/footer.php'; 
?>