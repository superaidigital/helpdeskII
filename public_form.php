<?php
$page_title = "แจ้งปัญหา / ขอคำปรึกษา";
require_once 'includes/db.php'; // เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล

// ดึงข้อมูลหมวดหมู่จากฐานข้อมูล
$categories_result = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY id ASC");
$categories = [];
if ($categories_result) {
    while($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - อบจ.ศรีสะเกษ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Animation for elements fading in and sliding up */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.5s ease-out forwards; }

        /* Styling for the main container to apply a blurred glass effect */
        .form-container {
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        /* Drag-over effect for file upload area */
        .drag-over {
            border-color: #4f46e5; /* indigo-600 */
            background-color: #e0e7ff; /* indigo-100 */
        }
        
        /* AI Loading Animation Dots */
        .dots-container .dot { display: inline-block; width: 10px; height: 10px; background-color: #a5b4fc; border-radius: 50%; margin: 0 4px; animation: dot-bounce 1.4s infinite ease-in-out both; }
        .animate-dot1 { animation-delay: -0.32s; } .animate-dot2 { animation-delay: -0.16s; }
        @keyframes dot-bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1.0); } }
    </style>
</head>
<body class="font-sans bg-gradient-to-br from-indigo-100 via-purple-100 to-blue-100">
    
    <main class="min-h-screen flex flex-col items-center justify-center py-10 px-4" 
          x-data="formWizard()">

        <div class="w-full max-w-4xl">
            <div class="text-center mb-8">
                <img src="assets/images/LogoSSKPao.png" alt="Logo" class="h-20 w-20 mx-auto mb-2">
                <h1 class="text-3xl font-bold text-gray-800">ระบบแจ้งปัญหาและให้คำปรึกษาด้าน IT</h1>
                <p class="text-gray-600 mt-1">องค์การบริหารส่วนจังหวัดศรีสะเกษ</p>
            </div>

            <div class="flex items-center justify-center mb-8">
                <div class="flex items-center">
                    <div class="flex items-center" :class="step === 1 ? 'text-indigo-600' : 'text-gray-500'">
                        <div class="rounded-full transition duration-500 ease-in-out h-10 w-10 border-2 flex items-center justify-center" :class="step >= 1 ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-gray-300'">
                            <i class="fa-solid fa-th-large"></i>
                        </div>
                        <div class="text-center ml-3"><div class="font-semibold">เลือกหมวดหมู่</div></div>
                    </div>
                </div>
                <div class="flex-auto border-t-2 transition duration-500 ease-in-out mx-4" :class="step >= 2 ? 'border-indigo-600' : 'border-gray-300'"></div>
                <div class="flex items-center" :class="step === 2 ? 'text-indigo-600' : 'text-gray-500'">
                     <div class="rounded-full transition duration-500 ease-in-out h-10 w-10 border-2 flex items-center justify-center" :class="step >= 2 ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-gray-300'">
                        <i class="fa-solid fa-file-alt"></i>
                    </div>
                    <div class="text-center ml-3"><div class="font-semibold">กรอกรายละเอียด</div></div>
                </div>
            </div>

            <div class="form-container p-8 rounded-2xl shadow-xl border border-white/50">
                <div x-show="step === 1" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform -translate-x-10" x-transition:enter-end="opacity-100 transform translate-x-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 transform translate-x-0" x-transition:leave-end="opacity-0 transform translate-x-10">
                    <div class="text-center">
                        <h2 class="text-2xl font-semibold text-gray-700">กรุณาเลือกหมวดหมู่ของปัญหา</h2>
                        <p class="text-gray-500 mt-1">เพื่อการช่วยเหลือที่รวดเร็วและตรงจุด</p>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6 mt-8">
                        <?php foreach ($categories as $index => $cat): ?>
                        <button @click="selectCategory('<?php echo htmlspecialchars($cat['name']); ?>', '<?php echo htmlspecialchars($cat['icon']); ?>')" 
                                class="category-card text-center p-6 bg-white/50 rounded-xl shadow-md hover:shadow-xl hover:bg-white transition-all transform hover:-translate-y-2 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 opacity-0 fade-in"
                                style="animation-delay: <?php echo $index * 100; ?>ms;">
                            <i class="fa-solid <?php echo $cat['icon']; ?> text-4xl text-indigo-500"></i>
                            <h3 class="mt-4 font-semibold text-lg text-gray-800"><?php echo htmlspecialchars($cat['name']); ?></h3>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div x-show="step === 2" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-x-10" x-transition:enter-end="opacity-100 transform translate-x-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 transform translate-x-0" x-transition:leave-end="opacity-0 transform -translate-x-10">
                    <form id="new-issue-form" @submit.prevent="submitForm" action="submit_issue.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                        <div class="border-b pb-4 flex items-center gap-4">
                            <i :class="selectedIcon" class="text-3xl text-indigo-600"></i>
                            <div>
                                <label class="text-sm font-medium text-gray-500">หมวดหมู่ที่เลือก</label>
                                <p class="font-bold text-xl text-gray-800" x-text="selectedCategory"></p>
                            </div>
                        </div>
                        <input type="hidden" name="category" :value="selectedCategory">
                        <input type="hidden" name="ai_interaction_id" :value="ai.interactionId">
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label for="reporter-name" class="block text-sm font-medium text-gray-700">ชื่อผู้แจ้ง</label>
                                <input type="text" id="reporter-name" name="reporter_name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="reporter-contact" class="block text-sm font-medium text-gray-700">ติดต่อ (เบอร์โทร/ไลน์)</label>
                                <input type="text" id="reporter-contact" name="reporter_contact" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>

                        <div>
                            <label for="issue-title" class="block text-sm font-medium text-gray-700">หัวข้อปัญหา (สรุปสั้นๆ)</label>
                            <input type="text" id="issue-title" name="title" x-model.debounce.500ms="issueTitle" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="issue-description" class="block text-sm font-medium text-gray-700">รายละเอียดปัญหา</label>
                            <textarea id="issue-description" name="description" rows="4" x-model.debounce.500ms="issueDescription" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>

                        <div x-show="issueTitle.trim().length > 5 && issueDescription.trim().length > 10" x-transition class="p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                            <div x-show="!ai.isLoading && !ai.suggestion">
                                <button type="button" @click="getAiSuggestion()" class="w-full text-left flex items-center justify-between p-2 text-indigo-700 font-semibold rounded-md hover:bg-indigo-100 transition-colors">
                                    <span><i class="fa-solid fa-wand-magic-sparkles mr-2"></i>AI ช่วยแนะนำวิธีแก้ปัญหาเบื้องต้น</span>
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            </div>
                            <div x-show="ai.isLoading" class="text-center text-gray-600 py-4">
                                <div class="dots-container"><span class="dot animate-dot1"></span><span class="dot animate-dot2"></span><span class="dot animate-dot3"></span></div>
                                <p class="mt-2 text-sm font-semibold">AI กำลังคิด กรุณารอสักครู่...</p>
                            </div>
                            <div x-show="ai.suggestion" x-transition class="space-y-2">
                                <pre class="font-sans whitespace-pre-wrap text-gray-800" x-text="ai.suggestion"></pre>
                                <p class="text-xs text-center pt-2 border-t text-gray-500 mt-3">หากคำแนะนำนี้ไม่สามารถแก้ปัญหาได้ กรุณากรอกข้อมูลส่วนที่เหลือและกด "ส่งเรื่อง"</p>
                            </div>
                        </div>

                        <div>
                            <label for="issue-urgency" class="block text-sm font-medium text-gray-700">ความเร่งด่วน</label>
                            <select id="issue-urgency" name="urgency" class="mt-1 block w-full pl-3 pr-10 py-2.5 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option>สามารถรอได้</option>
                                <option selected>ปกติ</option>
                                <option>ด่วนมาก</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">แนบไฟล์ (ถ้ามี)</label>
                            <div @dragover.prevent="isDragging = true" @dragleave.prevent="isDragging = false" @drop.prevent="dropHandler" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md transition-colors" :class="{ 'drag-over': isDragging }">
                                <div class="space-y-1 text-center"><i class="fa-solid fa-cloud-arrow-up text-4xl text-gray-400"></i><div class="flex text-sm text-gray-600"><label for="issue-files" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500"><span>อัปโหลดไฟล์</span><input id="issue-files" name="issue_files[]" type="file" class="sr-only" multiple @change="addFiles($event.target.files)"></label><p class="pl-1">หรือลากมาวาง</p></div><p class="text-xs text-gray-500">รองรับรูปภาพ, เอกสาร, PDF, ZIP</p></div>
                            </div>
                            <div id="file-list-display" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <template x-for="(file, index) in files" :key="index">
                                     <div class="flex items-center justify-between bg-gray-50 border p-2 rounded-lg text-sm" x-show="!file.removing" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-90" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-90">
                                        <div class="flex items-center truncate"><i class="fa-solid fa-file text-2xl mr-3 w-6 text-center text-gray-400"></i><div class="truncate"><span class="font-medium text-gray-800 truncate block" x-text="file.name"></span><span class="text-gray-500 text-xs" x-text="`${(file.size / 1024).toFixed(1)} KB`"></span></div></div>
                                        <button type="button" @click="removeFile(index)" class="text-red-500 hover:text-red-700 ml-2 flex-shrink-0"><i class="fa-solid fa-times-circle"></i></button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="flex justify-between items-center pt-4">
                            <button type="button" @click="step = 1; ai.suggestion = ''" class="px-6 py-2 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                                <i class="fa-solid fa-chevron-left mr-2"></i>ย้อนกลับ
                            </button>
                            <button type="submit" class="px-8 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition-transform transform hover:scale-105 shadow-lg">
                                ส่งเรื่อง <i class="fa-solid fa-paper-plane ml-2"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center mt-6">
                <a href="index.php" class="text-sm text-gray-600 hover:text-indigo-600 transition-colors">กลับหน้าแรก</a>
            </div>
        </div>
    </main>

    <script>
        function formWizard() {
            return {
                step: 1,
                selectedCategory: '',
                selectedIcon: '',
                files: [],
                isDragging: false,
                issueTitle: '',
                issueDescription: '',
                ai: { isLoading: false, suggestion: '', interactionId: null },
                selectCategory(name, icon) { this.selectedCategory = name; this.selectedIcon = 'fa-solid ' + icon; this.step = 2; },
                addFiles(fileList) { for (const file of fileList) { if (!this.files.some(f => f.name === file.name && f.size === file.size)) { this.files.push(file); } } this.updateFileInput(); },
                removeFile(index) { this.files[index].removing = true; setTimeout(() => { this.files.splice(index, 1); this.updateFileInput(); }, 300); },
                dropHandler(e) { this.isDragging = false; this.addFiles(e.dataTransfer.files); },
                updateFileInput() { const dt = new DataTransfer(); this.files.forEach(file => dt.items.add(file)); document.getElementById('issue-files').files = dt.files; },
                getAiSuggestion() {
                    this.ai.isLoading = true;
                    this.ai.suggestion = '';
                    this.ai.interactionId = null;
                    fetch('public_ai_helper.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ title: this.issueTitle, description: this.issueDescription })
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.ai.suggestion = data.suggestion || 'ขออภัย, ไม่สามารถให้คำแนะนำได้ในขณะนี้';
                        if (data.interactionId) {
                            this.ai.interactionId = data.interactionId;
                        }
                    })
                    .catch(() => { this.ai.suggestion = 'เกิดข้อผิดพลาดในการเชื่อมต่อกับผู้ช่วย AI'; })
                    .finally(() => { this.ai.isLoading = false; });
                },
                submitForm() { document.getElementById('new-issue-form').submit(); }
            }
        }
    </script>
</body>
</html>