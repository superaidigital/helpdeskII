<?php
$page_title = "แดชบอร์ดผู้ดูแลระบบ";
require_once 'includes/functions.php';
check_auth(['admin']);
require_once 'includes/header.php'; 

// --- ส่วนของการดึงข้อมูลสำหรับ Dashboard ---

// 1. Stat Cards Data
$total_issues_q = $conn->query("SELECT COUNT(id) as total FROM issues");
$total_issues = $total_issues_q->fetch_assoc()['total'] ?? 0;
$inprogress_issues_q = $conn->query("SELECT COUNT(id) as total FROM issues WHERE status = 'in_progress'");
$inprogress_issues = $inprogress_issues_q->fetch_assoc()['total'] ?? 0;
$pending_issues_q = $conn->query("SELECT COUNT(id) as total FROM issues WHERE status = 'pending'");
$pending_issues = $pending_issues_q->fetch_assoc()['total'] ?? 0;
$today_done_q = $conn->query("SELECT COUNT(id) as total FROM issues WHERE status = 'done' AND DATE(completed_at) = CURDATE()");
$today_done = $today_done_q->fetch_assoc()['total'] ?? 0;

// 2. Data for 7-Day Trend Chart (Line Chart)
$trend_q = $conn->query("
    SELECT DATE(created_at) as issue_date, COUNT(id) as total 
    FROM issues 
    WHERE created_at >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(created_at) 
    ORDER BY issue_date ASC
");

// --- MODIFICATION START: Thai Date Formatting ---
$thai_days_short = ["อา", "จ", "อ", "พ", "พฤ", "ศ", "ส"];
$thai_months_short = [1=>"ม.ค.", 2=>"ก.พ.", 3=>"มี.ค.", 4=>"เม.ย.", 5=>"พ.ค.", 6=>"มิ.ย.", 7=>"ก.ค.", 8=>"ส.ค.", 9=>"ก.ย.", 10=>"ต.ค.", 11=>"พ.ย.", 12=>"ธ.ค."];

$trend_data = [];
$trend_labels = [];
// Create a template for the last 7 days
for ($i = 6; $i >= 0; $i--) {
    $date_obj = new DateTime("-$i days");
    $date = $date_obj->format('Y-m-d');
    
    $day_of_week = $date_obj->format('w'); // 0 (for Sunday) through 6 (for Saturday)
    $day_number = $date_obj->format('j');
    $month_number = (int)$date_obj->format('n');
    $trend_labels[] = $thai_days_short[$day_of_week] . ", " . $day_number . " " . $thai_months_short[$month_number];
    
    $trend_data[$date] = 0;
}
// --- MODIFICATION END ---

while($row = $trend_q->fetch_assoc()){
    $trend_data[$row['issue_date']] = $row['total'];
}
$trend_values_json = json_encode(array_values($trend_data));
$trend_labels_json = json_encode($trend_labels);


// 3. Data for Category Chart (Doughnut Chart)
$category_q = $conn->query("SELECT category, COUNT(id) as total FROM issues GROUP BY category ORDER BY total DESC");
$category_labels = []; $category_values = [];
while($row = $category_q->fetch_assoc()){
    $category_labels[] = $row['category'];
    $category_values[] = $row['total'];
}
$category_labels_json = json_encode($category_labels);
$category_values_json = json_encode($category_values);


// 4. Data for Staff Workload Table
$staff_workload_q = $conn->query("
    SELECT u.fullname, COUNT(i.id) as open_tickets
    FROM users u
    LEFT JOIN issues i ON u.id = i.assigned_to AND i.status IN ('pending', 'in_progress', 'awaiting_parts')
    WHERE u.role IN ('it', 'admin')
    GROUP BY u.id
    ORDER BY open_tickets DESC, u.fullname ASC
");

// 5. Recent Issues
$recent_issues_q = $conn->query("SELECT id, title, reporter_name FROM issues ORDER BY created_at DESC LIMIT 5");

// 6. Data for AI Consultation Stats (Last 30 Days)
$ai_stats_q = $conn->query("
    SELECT
        COUNT(id) as total_consultations,
        SUM(CASE WHEN ticket_created = 0 THEN 1 ELSE 0 END) as tickets_deflected
    FROM ai_interactions
    WHERE created_at >= CURDATE() - INTERVAL 30 DAY
");
$ai_stats = $ai_stats_q->fetch_assoc();
$total_consultations = $ai_stats['total_consultations'] ?? 0;
$tickets_deflected = $ai_stats['tickets_deflected'] ?? 0;
$deflection_rate = ($total_consultations > 0) ? ($tickets_deflected / $total_consultations) * 100 : 0;
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="space-y-8">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center gap-4"><div class="bg-indigo-100 p-4 rounded-full"><i class="fa-solid fa-layer-group text-2xl text-indigo-600"></i></div><div><h3 class="text-gray-700">เรื่องทั้งหมด</h3><p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $total_issues; ?></p></div></div>
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center gap-4"><div class="bg-blue-100 p-4 rounded-full"><i class="fa-solid fa-spinner text-2xl text-blue-600"></i></div><div><h3 class="text-gray-700">กำลังดำเนินการ</h3><p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $inprogress_issues; ?></p></div></div>
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center gap-4"><div class="bg-amber-100 p-4 rounded-full"><i class="fa-solid fa-hourglass-start text-2xl text-amber-600"></i></div><div><h3 class="text-gray-700">รอตรวจสอบ</h3><p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $pending_issues; ?></p></div></div>
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center gap-4"><div class="bg-green-100 p-4 rounded-full"><i class="fa-solid fa-check-circle text-2xl text-green-600"></i></div><div><h3 class="text-gray-700">เสร็จสิ้นวันนี้</h3><p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $today_done; ?></p></div></div>
    </div>
    
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold text-gray-800">ทางลัด (Quick Actions)</h3>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
            <a href="admin_users.php" class="block p-4 bg-gray-50 hover:bg-indigo-50 rounded-lg transition-colors text-center font-medium text-gray-700 hover:text-indigo-600"><i class="fa-solid fa-users text-2xl text-indigo-500"></i><p class="mt-2">จัดการผู้ใช้</p></a>
            <a href="it_dashboard.php" class="block p-4 bg-gray-50 hover:bg-blue-50 rounded-lg transition-colors text-center font-medium text-gray-700 hover:text-blue-600"><i class="fa-solid fa-list-check text-2xl text-blue-500"></i><p class="mt-2">ดูรายการปัญหา</p></a>
            <a href="admin_reports.php" class="block p-4 bg-gray-50 hover:bg-green-50 rounded-lg transition-colors text-center font-medium text-gray-700 hover:text-green-600"><i class="fa-solid fa-chart-line text-2xl text-green-500"></i><p class="mt-2">ดูรายงานสรุป</p></a>
            <a href="admin_ai_analytics.php" class="block p-4 bg-gray-50 hover:bg-purple-50 rounded-lg transition-colors text-center font-medium text-gray-700 hover:text-purple-600"><i class="fa-solid fa-brain text-2xl text-purple-500"></i><p class="mt-2">วิเคราะห์โดย AI</p></a>
            <a href="admin_kb.php" class="block p-4 bg-gray-50 hover:bg-teal-50 rounded-lg transition-colors text-center font-medium text-gray-700 hover:text-teal-600"><i class="fa-solid fa-book-open text-2xl text-teal-500"></i><p class="mt-2">ฐานความรู้</p></a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-md"><h3 class="text-lg font-semibold text-gray-800 mb-4">แนวโน้มการแจ้งปัญหา 7 วันล่าสุด</h3><div class="h-64"><canvas id="trendChart"></canvas></div></div>
            <div class="bg-white rounded-lg shadow-md"><h3 class="text-lg font-semibold text-gray-800 p-4 border-b">5 รายการล่าสุด</h3><ul class="divide-y divide-gray-200"><?php while($issue = $recent_issues_q->fetch_assoc()): ?><li class="p-4 flex justify-between items-center hover:bg-gray-50"><div><p class="font-medium text-gray-800"><?php echo htmlspecialchars($issue['title']); ?></p><p class="text-sm text-gray-500">ผู้แจ้ง: <?php echo htmlspecialchars($issue['reporter_name']); ?></p></div><a href="issue_view.php?id=<?php echo $issue['id']; ?>" class="text-sm text-indigo-600 hover:text-indigo-800 font-semibold">ดูรายละเอียด</a></li><?php endwhile; ?></ul></div>
        </div>
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 p-4 border-b">สถิติการปรึกษา AI (30 วันล่าสุด)</h3>
                <div class="p-4 space-y-4">
                    <div class="flex justify-between items-center"><span class="text-sm text-gray-600 flex items-center"><i class="fa-solid fa-comment-dots text-indigo-500 mr-2"></i> ให้คำแนะนำทั้งหมด</span><span class="font-bold text-indigo-600 text-lg"><?php echo number_format($total_consultations); ?> ครั้ง</span></div>
                     <div class="flex justify-between items-center"><span class="text-sm text-gray-600 flex items-center"><i class="fa-solid fa-shield-halved text-green-500 mr-2"></i> ช่วยแก้ปัญหาได้</span><span class="font-bold text-green-600 text-lg"><?php echo number_format($tickets_deflected); ?> ครั้ง</span></div>
                    <div>
                        <p class="text-xs text-gray-500 text-center mb-1">อัตราการลดจำนวน Ticket (Deflection Rate)</p>
                        <div class="w-full bg-gray-200 rounded-full h-5"><div class="bg-green-500 h-5 rounded-full text-center text-white text-xs font-bold leading-5" style="width: <?php echo number_format($deflection_rate, 1); ?>%"><?php echo number_format($deflection_rate, 1); ?>%</div></div>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md"><h3 class="text-lg font-semibold text-gray-800 mb-4">สัดส่วนปัญหาตามหมวดหมู่</h3><div class="h-64"><canvas id="categoryChart"></canvas></div></div>
            <div class="bg-white rounded-lg shadow-md"><h3 class="text-lg font-semibold text-gray-800 p-4 border-b">ภาระงานของเจ้าหน้าที่ (ที่ยังไม่เสร็จ)</h3><ul class="divide-y divide-gray-200"><?php while($staff = $staff_workload_q->fetch_assoc()): ?><li class="p-4 flex justify-between items-center"><p class="font-medium text-gray-800"><?php echo htmlspecialchars($staff['fullname']); ?></p><span class="px-3 py-1 text-sm font-bold rounded-full <?php echo ($staff['open_tickets'] > 5) ? 'bg-red-200 text-red-800' : 'bg-blue-100 text-blue-800'; ?>"><?php echo $staff['open_tickets']; ?></span></li><?php endwhile; ?></ul></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sarabunFont = { family: 'Sarabun' };
    const chartDefaults = { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { font: sarabunFont } } } };

    // Trend Chart (Line)
    new Chart(document.getElementById('trendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo $trend_labels_json; ?>,
            datasets: [{
                label: 'จำนวนเรื่อง',
                data: <?php echo $trend_values_json; ?>,
                fill: true, borderColor: 'rgb(79, 70, 229)', tension: 0.2, backgroundColor: 'rgba(79, 70, 229, 0.1)'
            }]
        },
        options: { ...chartDefaults, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { ticks: { font: sarabunFont } } }, plugins: { legend: { display: false } } }
    });

    // Category Chart (Doughnut)
    new Chart(document.getElementById('categoryChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?php echo $category_labels_json; ?>,
            datasets: [{
                data: <?php echo $category_values_json; ?>,
                backgroundColor: ['#4F46E5', '#10B981', '#F59E0B', '#3B82F6', '#EF4444', '#6B7280'],
                hoverOffset: 4
            }]
        },
        options: { ...chartDefaults, plugins: { legend: { position: 'top', ...chartDefaults.plugins.legend } } }
    });
});
</script>

<?php 
$conn->close();
require_once 'includes/footer.php'; 
?>