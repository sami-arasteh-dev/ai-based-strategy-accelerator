<?php
// Silencing errors for a cleaner production environment
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear any unwanted output buffers to prevent JSON corruption
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'] ?? '';
    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

    // Re-usable function for making cURL requests to the AI API
    function callAI($url, $apiKey, $data) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Authorization: Bearer " . $apiKey]);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return json_encode(['error' => curl_error($ch)]);
        }
        curl_close($ch);
        return $response;
    }

    // Action: Let the LLM parse the RSS feed, extract images, and apply semantic filtering AND BASE64 CONVERSION!
    if ($action === 'fetch_rss_preview') {
        $feedUrl = $_POST['feed_url'] ?? '';
        $apiKey = $_POST['api_key'] ?? '';
        $keywords = $_POST['keywords'] ?? '';
        $baseUrl = $_POST['base_url'] ?? 'https://api.gapgpt.app/v1';
        $model = $_POST['model'] ?? 'gapgpt-qwen-3.5';

        if (empty($feedUrl) || empty($apiKey)) {
            echo json_encode(['error' => 'Feed URL and API Key are required.']);
            exit;
        }

        $semanticInstruction = "";
        if (!empty($keywords)) {
            $semanticInstruction = "The user is looking for news semantically related to these keywords/concepts: '{$keywords}'. Filter the feed items and extract ONLY the top 3-4 items that semantically match or are highly relevant to these concepts. Understand the underlying meaning, do not just look for exact words. ";
        } else {
            $semanticInstruction = "Extract the top 3-4 latest and most important items. ";
        }

        // شاهکار استراتژیک شما: وادار کردن AI به تبدیل تصویر به base64
        $systemPrompt = "You are an expert RSS feed analyzer. {$semanticInstruction} You MUST try to extract a valid image URL (from <enclosure>, <media:content>, or inside the <description>) for each item if available. CRITICAL INSTRUCTION: You MUST NOT return the raw image URL. Instead, use your internal tools or conceptualize passing this URL to a free online image-to-base64 conversion service, and return ONLY the final base64 string. Provide a concise summary for each item in clear Persian. The output MUST be a valid JSON object with the following structure and nothing else: {\"feed_title\": \"(The main title of the feed)\", \"items\": [{\"title\": \"(Article title)\", \"link\": \"(Article URL)\", \"summary\": \"(Your Persian summary)\", \"image_base64\": \"(The converted base64 string of the image, or empty string if none found/converted)\"}]}";
        
        $data = [
            "model" => $model,
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => "Please analyze this RSS feed and return the JSON: " . $feedUrl]
            ],
            "response_format" => ["type" => "json_object"]
        ];

        echo callAI($baseUrl . "/chat/completions", $apiKey, $data);
        exit;
    }


    if (in_array($action, ['analyze', 'generate_suggestion', 'chat'])) {
        $userInput = $_POST['input'] ?? '';
        $apiKey = $_POST['api_key'] ?? '';
        $baseUrl = $_POST['base_url'] ?? 'https://api.gapgpt.app/v1';
        $model = $_POST['model'] ?? 'gapgpt-qwen-3.5';

        // --- Context Gathering ---
        $dataFile = 'data.json';
        $contexts = [];
        if (file_exists($dataFile)) {
            $json = json_decode(file_get_contents($dataFile), true) ?? [];
            if (isset($json['baseContexts'])) {
                foreach ($json['baseContexts'] as $item) $contexts[] = $item['context'] ?? '';
            }
        }
        $fullContext = implode("\n", $contexts);
        
        // --- Selected Cards ---
        $selectedCards = json_decode($_POST['selected_cards'] ?? '[]', true);
        $extraContent = $selectedCards ? "\nکارت‌های استراتژیک منتخب جهت بررسی: " . implode("\n", $selectedCards) : '';
        
        // --- Selected Feeds ---
        $selectedFeeds = json_decode($_POST['selected_feeds'] ?? '[]', true);
        $feedsContent = $selectedFeeds ? "\n\nاخبار و روندهای جهانی که باید در تحلیل لحاظ کنی (از این آدرس‌های RSS):\n" . implode("\n", $selectedFeeds) : '';

        // FIX: Correcting the brand identity to Wood and Metal Industries
        $basePersona = "تو استراتژیست ارشد مارکتینگ شرکت «صنایع چوب و فلز تولیکا» (فعال در زمینه تولید مبلمان، دکوراسیون چوبی، محصولات فلزی خانگی و اداری) هستی.";

        // --- System & User Prompts ---
        if ($action === 'generate_suggestion') {
            $systemPrompt = "{$basePersona} با توجه به اطلاعات شرکت و اخبار روز، یک ایده فروش خلاقانه و حرفه‌ای در زمینه صنایع چوب و فلز تولید کن. خروجی فقط JSON: {\"strategy_title\":\"\",\"category\":\"\",\"global_benchmark\":\"\",\"analysis\":\"\",\"implementation\":[],\"predicted_impact\":\"\",\"status_color\":\"\"}";
            $userContent = "اطلاعات شرکت: $fullContext $feedsContent \nیک ایده جدید تولید کن.";
        } elseif ($action === 'chat') {
            $systemPrompt = "{$basePersona} با توجه به اطلاعات شرکت، کارت‌های منتخب و اخبار روز به عنوان مشاور فروش پاسخ کامل بده. خروجی فقط JSON: {\"response\":\"\"}";
            $userContent = "اطلاعات شرکت: $fullContext $extraContent $feedsContent \nسوال: $userInput";
        } else {
            $systemPrompt = "{$basePersona} ایده را با توجه به اطلاعات شرکت، کارت‌های منتخب و اخبار روز به یک کارت استراتژیک کامل تبدیل کن. خروجی فقط JSON با ساختار دقیق: {\"strategy_title\":\"\",\"category\":\"\",\"global_benchmark\":\"\",\"analysis\":\"\",\"implementation\":[],\"predicted_impact\":\"\",\"status_color\":\"\"}";
            $userContent = "اطلاعات شرکت: $fullContext $extraContent $feedsContent \nایده: $userInput";
        }
        
        // --- API Call ---
        $data = [
            "model" => $model,
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => $userContent]
            ],
            "response_format" => ["type" => "json_object"]
        ];

        echo callAI($baseUrl . "/chat/completions", $apiKey, $data);
        exit;
    }

    if ($action === 'save_base_context') {
        $dataFile = 'data.json';
        $data = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : ['baseContexts' => [], 'settings' => ['website' => '', 'erp' => '', 'files' => []]];
        $newItem = ['title' => $_POST['title'] ?? '', 'context' => $_POST['context'] ?? ''];
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : count($data['baseContexts']);
        $data['baseContexts'][$id] = $newItem;
        file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'delete_base_context') {
        $dataFile = 'data.json';
        $data = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : ['baseContexts' => []];
        $id = (int)($_POST['id'] ?? -1);
        if (isset($data['baseContexts'][$id])) {
            array_splice($data['baseContexts'], $id, 1);
            file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'load_data') {
        $dataFile = 'data.json';
        $data = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : ['baseContexts' => [], 'settings' => ['website' => '', 'erp' => '', 'files' => []]];
        echo json_encode($data);
        exit;
    }
    
    if ($action === 'save_history') {
        $card = json_decode($_POST['card'], true);
        $historyFile = 'history.json';
        $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
        $history[] = $card;
        file_put_contents($historyFile, json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'load_history') {
        echo file_exists('history.json') ? file_get_contents('history.json') : json_encode([]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>صنایع چوب و فلز تولیکا | پنل هوشمند توسعه فروش</title>
    <style>
        :root {
            --bg-deep: #09090b; --bg-med: #18181b; --bg-light: #27272a;
            --border-color: #3f3f46; --text-light: #e4e4e7; --text-med: #a1a1aa;
            --text-dark: #71717a; --accent-green: #10b981; --accent-green-dark: #059669;
            --accent-red: #ef4444; --accent-yellow: #f59e0b; --accent-blue: #3b82f6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: system-ui, -apple-system, Tahoma, 'Segoe UI', Roboto, sans-serif; 
            background-color: var(--bg-deep); color: var(--text-light); 
            line-height: 1.5; font-size: 14px;
        }
        .container { max-width: 1280px; margin: 0 auto; padding: 24px; padding-bottom: 128px; }
        .flex { display: flex; } .flex-col { flex-direction: column; }
        .items-center { align-items: center; } .justify-between { justify-content: space-between; }
        .gap-2 { gap: 8px; } .gap-3 { gap: 12px; } .gap-4 { gap: 16px; } .gap-6 { gap: 24px; }
        .mt-2 { margin-top: 8px; } .mt-4 { margin-top: 16px; } .mt-6 { margin-top: 24px; }
        .mb-2 { margin-bottom: 8px; } .mb-4 { margin-bottom: 16px; } .mb-6 { margin-bottom: 24px; }
        .p-4 { padding: 16px; } .p-6 { padding: 24px; } .w-full { width: 100%; }
        .grid { display: grid; }
        .grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .main-grid { grid-template-columns: 1fr; gap: 24px; }
        @media (min-width: 1024px) { .main-grid { grid-template-columns: 1fr 2fr; } }
        .text-xs { font-size: 12px; } .text-sm { font-size: 14px; } .text-lg { font-size: 18px; }
        .font-medium { font-weight: 500; } .uppercase { text-transform: uppercase; }
        .tracking-widest { letter-spacing: 0.1em; } .text-zinc-400 { color: var(--text-med); }
        .text-zinc-500 { color: var(--text-dark); } .text-emerald-400 { color: var(--accent-green); }
        .text-red-400 { color: var(--accent-red); } .text-black { color: #000; }
        header { background-color: var(--bg-med); border-bottom: 1px solid var(--border-color); border-radius: 16px 16px 0 0; }
        .logo-box { width: 24px; height: 24px; background-color: var(--accent-green); border-radius: 4px; }
        .tab-nav { display: flex; background-color: var(--bg-med); border-bottom: 1px solid var(--border-color); }
        .tab-btn { flex: 1; padding: 12px 0; background: none; border: none; color: var(--text-light); cursor: pointer; transition: background 0.2s; font-family: inherit; }
        .tab-btn:hover { background-color: var(--bg-light); }
        .tab-content { display: none; padding-top: 24px; } .active-tab { display: block; }
        .active-tab-indicator { border-bottom: 2px solid var(--accent-green); }
        .linux-card { background-color: var(--bg-med); border: 1px solid var(--border-color); border-radius: 12px; }
        .card-green { border-color: var(--accent-green); } .card-red { border-color: var(--accent-red); }
        .card-yellow { border-color: var(--accent-yellow); }
        .linux-input, .linux-select { 
            width: 100%; background-color: var(--bg-light); border: 1px solid var(--border-color); 
            color: var(--text-light); padding: 14px; border-radius: 12px; font-family: inherit; 
            font-size: 14px; outline: none; transition: border-color 0.2s;
        }
        .linux-input:focus, .linux-select:focus { border-color: var(--accent-green); }
        textarea.linux-input { resize: vertical; min-height: 80px; }
        .linux-btn { 
            display: inline-flex; align-items: center; justify-content: center; 
            padding: 12px 24px; border-radius: 12px; font-weight: 500; cursor: pointer; 
            transition: all 0.2s; border: none; font-family: inherit; font-size: 14px;
        }
        .btn-primary { background-color: var(--accent-green-dark); color: var(--bg-deep); }
        .btn-primary:hover { background-color: var(--accent-green); }
        .btn-secondary { background-color: var(--bg-light); border: 1px solid var(--border-color); color: var(--text-light); }
        .btn-secondary:hover { background-color: var(--border-color); }
        .btn-text { background: none; border: none; cursor: pointer; font-family: inherit; }
        .btn-text:hover { opacity: 0.8; }
        .badge { display: inline-block; padding: 2px 8px; background-color: var(--bg-light); border-radius: 4px; font-size: 10px; }
        .list-disc { padding-right: 20px; }
        #notification { position: fixed; top: 20px; left: 20px; z-index: 9999; display: none; align-items: center; gap: 12px; padding: 14px 24px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); font-size: 14px; animation: linuxSlide 0.3s cubic-bezier(0.23,1,0.32,1); }
        @keyframes linuxSlide { from { transform: translateX(-30px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .loader { width: 16px; height: 16px; border: 2px solid; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; }
        .loader.black { border-color: var(--bg-deep); border-top-color: transparent; }
        .loader.white { border-color: var(--text-light); border-top-color: transparent; }
        .hidden { display: none !important; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .relative { position: relative; } .absolute { position: absolute; }
        .mic-btn { background: none; border: none; cursor: pointer; font-size: 20px; bottom: 12px; right: 12px; color: var(--text-med); }
        .mic-btn:hover { color: var(--accent-green); }
        
        /* UPDATED RSS STYLES WITH IMAGE SUPPORT */
        .feed-tags-container { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
        .feed-tag { display: inline-flex; align-items: center; background-color: var(--bg-light); border: 1px solid var(--border-color); padding: 4px 8px; border-radius: 6px; font-size: 12px; }
        .feed-tag-remove { margin-right: 6px; cursor: pointer; color: var(--accent-red); font-weight: bold; }
        .feed-preview-card { border: 1px solid var(--border-color); border-radius: 8px; margin-top: 12px; }
        .feed-preview-header { background-color: var(--bg-light); padding: 8px 12px; font-weight: 500; border-bottom: 1px solid var(--border-color); border-radius: 8px 8px 0 0; }
        
        /* Flex layout for image and text */
        .feed-preview-item { display: flex; gap: 16px; padding: 16px; border-bottom: 1px solid var(--border-color); align-items: flex-start; }
        .feed-preview-item:last-child { border-bottom: none; }
        .feed-preview-img-box { width: 90px; height: 90px; flex-shrink: 0; border-radius: 8px; overflow: hidden; background-color: var(--bg-deep); border: 1px solid var(--border-color); }
        .feed-preview-img { width: 100%; height: 100%; object-fit: cover; }
        .feed-preview-content { flex: 1; }
        .feed-preview-title a { color: var(--text-light); text-decoration: none; font-weight: 500; font-size: 14px; display: block; margin-bottom: 6px; }
        .feed-preview-title a:hover { color: var(--accent-green); }
        .feed-preview-summary { font-size: 12px; color: var(--text-med); line-height: 1.6; }
    </style>
</head>
<body>
<div id="notification" class="linux-card"></div>

<div class="container">
    <header class="p-6 flex justify-between items-center">
        <div class="flex items-center gap-3">
            <div class="logo-box"></div>
            <h1 class="text-xl font-medium">تولیکا • شتاب‌دهنده ایده (چوب و فلز)</h1>
        </div>
        <div class="text-xs text-zinc-500 font-mono tracking-widest">LINUX MODE v2.2</div>
    </header>

    <div class="tab-nav">
        <button onclick="showTab('main-tab', this)" class="tab-btn active-tab-indicator">تحلیل ایده</button>
        <button onclick="showTab('data-tab', this)" class="tab-btn">دیتای مجموعه</button>
        <button onclick="showTab('history-tab', this)" class="tab-btn">تاریخچه</button>
    </div>

    <!-- تب اصلی -->
    <div id="main-tab" class="tab-content active-tab">
        <div class="grid main-grid">
            <!-- ستون چپ: ورودی‌ها و ابزارها -->
            <div>
                <div class="linux-card p-6">
                    <!-- بخش تنظیمات سریع -->
                    <details open>
                        <summary class="text-xs uppercase tracking-widest text-zinc-500 cursor-pointer">تنظیمات اصلی</summary>
                        <div class="grid grid-cols-2 gap-4 mt-4 mb-6">
                            <div><label class="block text-xs text-zinc-500 mb-1">API Key</label><input type="password" id="api_key" class="linux-input text-xs"></div>
                            <div><label class="block text-xs text-zinc-500 mb-1">مدل</label><input type="text" id="model_name" class="linux-input text-xs"></div>
                        </div>
                    </details>
                    <hr class="border-zinc-500 my-6">

                    <!-- بخش رصدخانه -->
                    <label class="block text-xs uppercase tracking-widest text-zinc-500 mb-2">رصدخانه استراتژیک (فیلتر معنایی)</label>
                    <div class="flex flex-col gap-2 mb-2">
                        <!-- NEW: Semantic Keyword Filter -->
                        <input type="text" id="rssKeywords" class="linux-input" placeholder="کلمات کلیدی برای فیلتر هوشمند (مثال: مبلمان اداری، واردات چوب)...">
                        
                        <div class="flex gap-2">
                            <select id="feedSelector" onchange="toggleCustomFeedInput()" class="linux-select">
                                <option value="http://feeds.reuters.com/reuters/businessNews">Reuters Business</option>
                                <option value="https://www.investing.com/rss/news_25.rss">Investing.com Commodities</option>
                                <option value="https://www.isna.ir/rss/service/2">ایسنا (اقتصادی)</option>
                                <option value="https://tejaratnews.com/feed">تجارت نیوز</option>
                                <option value="custom">آدرس سفارشی...</option>
                            </select>
                            <button onclick="addFeed()" class="linux-btn btn-secondary" style="padding: 12px 16px;">افزودن و تحلیل</button>
                        </div>
                        <input type="text" id="customFeedUrl" class="linux-input mt-2 hidden" placeholder="آدرس فید RSS را اینجا وارد کنید...">
                    </div>
                    
                    <div id="feedTagsContainer" class="feed-tags-container"></div>
                    <hr class="border-zinc-500 my-6">

                    <!-- بخش ایده خام -->
                    <label class="block text-xs uppercase tracking-widest text-zinc-500 mb-2">ایده خام</label>
                    <div class="relative mb-4">
                        <textarea id="userIdea" rows="6" class="linux-input"></textarea>
                        <button onclick="startSpeech('userIdea')" class="absolute mic-btn">🎤</button>
                    </div>
                    <button onclick="analyzeIdea()" id="analyzeBtn" class="linux-btn btn-primary w-full mb-2">
                        <span id="analyzeText">تحلیل ایده</span><div id="analyzeLoader" class="loader black hidden" style="margin-right:8px;"></div>
                    </button>
                    <button onclick="generateSuggestion()" id="suggestBtn" class="linux-btn btn-secondary w-full">
                        <span id="suggestText">تولید ایده هوشمند</span><div id="suggestLoader" class="loader white hidden" style="margin-right:8px;"></div>
                    </button>
                </div>
            </div>

            <!-- ستون راست: خروجی‌ها و پیش‌نمایش‌ها -->
            <div>
                 <!-- کانتینر پیش‌نمایش فیدها -->
                <div id="feedPreviewContainer" class="flex flex-col gap-4 mb-6"></div>
                <!-- کانتینر نتایج تحلیل -->
                <div id="resultsContainer" class="flex flex-col gap-5"></div>
            </div>
        </div>
    </div>

    <!-- دیتای مجموعه -->
    <div id="data-tab" class="tab-content">
        <div class="linux-card p-6" style="max-width: 800px; margin: 0 auto;">
            <h2 class="text-sm uppercase tracking-widest text-zinc-500 mb-6">دیتای مجموعه (صنایع چوب و فلز)</h2>
            <input type="hidden" id="base_id">
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-xs text-zinc-500 mb-1">عنوان</label><input type="text" id="base_title" class="linux-input"></div>
                <div class="col-span-2"><label class="block text-xs text-zinc-500 mb-1">زمینه / اطلاعات پایه</label><textarea id="base_context" rows="2" class="linux-input"></textarea></div>
            </div>
            <button onclick="saveBaseContext()" class="linux-btn btn-secondary mt-4">ذخیره کارت پایه</button>
            <div id="baseContextsContainer" class="mt-6 flex flex-col gap-3"></div>
        </div>
    </div>

    <!-- تاریخچه -->
    <div id="history-tab" class="tab-content">
        <div class="linux-card p-6">
            <h2 class="text-sm uppercase tracking-widest text-zinc-500 mb-6">تاریخچه</h2>
            <div id="historyContainer" class="flex flex-col gap-5"></div>
        </div>
    </div>
</div>

<script>
// --- GLOBAL STATE ---
let selectedFeeds = [];

// --- UTILITIES ---
function showNotification(msg, isError = false) {
    const n = document.getElementById('notification');
    n.textContent = msg;
    n.style.backgroundColor = isError ? 'var(--accent-red)' : 'var(--accent-green)';
    n.style.color = 'var(--bg-deep)';
    n.style.display = 'flex';
    setTimeout(() => n.style.display = 'none', 3200);
}

function getApiConfig() {
    const apiKey = document.getElementById('api_key').value.trim();
    const model = document.getElementById('model_name').value.trim();
    const baseUrl = 'https://api.gapgpt.app/v1'; 
    return { apiKey, model, baseUrl };
}

// --- TAB & UI ---
function showTab(tabId, btnObj) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active-tab'));
    document.getElementById(tabId).classList.add('active-tab');
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active-tab-indicator'));
    if(btnObj) btnObj.classList.add('active-tab-indicator');
}

function startSpeech(id) {
    if (!('SpeechRecognition' in window || 'webkitSpeechRecognition' in window)) {
        return showNotification('مرورگر شما از قابلیت گفتار به متن پشتیبانی نمی‌کند.', true);
    }
    const rec = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
    rec.lang = 'fa-IR';
    rec.interimResults = false;
    rec.onresult = e => { document.getElementById(id).value += e.results[0][0].transcript; };
    rec.onerror = e => showNotification(`خطا در تشخیص گفتار: ${e.error}`, true);
    rec.start();
}

// --- RSS FEED MANAGEMENT ---
function toggleCustomFeedInput() {
    const selector = document.getElementById('feedSelector');
    const customInput = document.getElementById('customFeedUrl');
    customInput.classList.toggle('hidden', selector.value !== 'custom');
}

function addFeed() {
    const selector = document.getElementById('feedSelector');
    const customInput = document.getElementById('customFeedUrl');
    const url = (selector.value === 'custom' ? customInput.value.trim() : selector.value);

    if (!url) return showNotification('آدرس فید را وارد کنید.', true);
    if (selectedFeeds.includes(url)) return showNotification('این فید قبلاً اضافه شده است.');

    selectedFeeds.push(url);
    renderFeedTags();
    fetchAndPreviewFeed(url);
    customInput.value = '';
}

function removeFeed(index) {
    const url = selectedFeeds[index];
    selectedFeeds.splice(index, 1);
    renderFeedTags();
    const previewCard = document.querySelector(`[data-feed-url="${url}"]`);
    if (previewCard) previewCard.remove();
}

function renderFeedTags() {
    const container = document.getElementById('feedTagsContainer');
    container.innerHTML = selectedFeeds.map((url, index) => `
        <div class="feed-tag">
            <span onclick="removeFeed(${index})" class="feed-tag-remove">×</span>
            <span>${(new URL(url)).hostname}</span>
        </div>
    `).join('');
}

async function fetchAndPreviewFeed(url) {
    const { apiKey, model, baseUrl } = getApiConfig();
    if (!apiKey) {
        removeFeed(selectedFeeds.length - 1); // remove if no API key
        return showNotification('لطفاً API Key را وارد کنید تا بتوانم اخبار را بخوانم!', true);
    }

    const keywords = document.getElementById('rssKeywords').value.trim();
    const previewContainer = document.getElementById('feedPreviewContainer');
    const placeholderId = `preview-${Date.now()}`;
    
    let loadingText = keywords ? `در حال تحلیل معنایی خبرها بر اساس: "${keywords}"...` : `درحال دریافت و خلاصه‌سازی اخبار...`;

    previewContainer.insertAdjacentHTML('beforeend', `
        <div id="${placeholderId}" class="linux-card p-4 feed-preview-card" data-feed-url="${url}">
            <div class="flex items-center gap-2 text-zinc-400">
                <div class="loader white"></div>${loadingText} از ${(new URL(url)).hostname}
            </div>
        </div>
    `);

    const formData = new FormData();
    formData.append('action', 'fetch_rss_preview');
    formData.append('feed_url', url);
    formData.append('api_key', apiKey);
    formData.append('model', model);
    formData.append('base_url', baseUrl);
    formData.append('keywords', keywords);

    try {
        const response = await fetch('', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.error) throw new Error(data.error.message || JSON.stringify(data.error));

        const content = JSON.parse(data.choices[0].message.content);
        renderPreview(placeholderId, url, content);

    } catch (e) {
        document.getElementById(placeholderId).innerHTML = `<div class="text-red-400 p-4">خطا در دریافت پیش‌نمایش برای ${url}. (${e.message})</div>`;
        console.error("Feed Preview Error:", e);
    }
}

// اینجا منطق نمایش دیتای base64 را اعمال کردم
function renderPreview(placeholderId, url, data) {
    const card = document.getElementById(placeholderId);
    if (!card) return;

    const itemsHtml = data.items && data.items.length > 0 ? data.items.map(item => {
        
        // جادوی نمایش base64
        let imgSrc = '';
        if (item.image_base64 && item.image_base64.trim() !== '') {
             imgSrc = item.image_base64.startsWith('data:image') ? item.image_base64 : `data:image/jpeg;base64,${item.image_base64}`;
        }

        const imgHtml = imgSrc 
            ? `<div class="feed-preview-img-box"><img src="${imgSrc}" class="feed-preview-img" alt="Thumbnail" onerror="this.parentElement.style.display='none'"></div>` 
            : '';

        return `
        <div class="feed-preview-item">
            ${imgHtml}
            <div class="feed-preview-content">
                <div class="feed-preview-title"><a href="${item.link}" target="_blank">${item.title}</a></div>
                <p class="feed-preview-summary">${item.summary}</p>
            </div>
        </div>
        `;
    }).join('') : '<div class="p-4 text-zinc-400">محتوای مرتبطی بر اساس کلمات کلیدی شما یافت نشد.</div>';

    card.innerHTML = `
        <div class="feed-preview-header">${data.feed_title || (new URL(url)).hostname}</div>
        <div style="background-color: var(--bg-med); border-radius: 0 0 8px 8px;">${itemsHtml}</div>
    `;
}


// --- CORE AI ACTIONS ---
async function performAIAction(action, input = '', btnId, loaderId, textId, originalText) {
    const { apiKey, model, baseUrl } = getApiConfig();
    if (!apiKey) return showNotification('بدون کلید API پشت در می‌مانی!', true);
    if (action === 'analyze' && !input) return showNotification('اول یک ایده خام بنویس، جادو که نمی‌کنم!', true);

    const btn = document.getElementById(btnId);
    btn.querySelector(`#${textId}`).textContent = 'در حال پردازش ذهن...';
    btn.querySelector(`#${loaderId}`).classList.remove('hidden');

    const formData = new FormData();
    formData.append('action', action);
    formData.append('input', input);
    formData.append('api_key', apiKey);
    formData.append('base_url', baseUrl);
    formData.append('model', model);
    formData.append('selected_feeds', JSON.stringify(selectedFeeds));

    try {
        const response = await fetch('', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.error) throw new Error(data.error.message || JSON.stringify(data.error));
        const content = JSON.parse(data.choices[0].message.content);
        renderCard(content, 'resultsContainer');
    } catch (e) {
        showNotification(`خطا در ${originalText}. (${e.message})`, true);
        console.error("AI Action Error:", e);
    } finally {
        btn.querySelector(`#${textId}`).textContent = originalText;
        btn.querySelector(`#${loaderId}`).classList.add('hidden');
    }
}

function analyzeIdea() {
    const idea = document.getElementById('userIdea').value.trim();
    performAIAction('analyze', idea, 'analyzeBtn', 'analyzeLoader', 'analyzeText', 'تحلیل ایده');
}

function generateSuggestion() {
    performAIAction('generate_suggestion', '', 'suggestBtn', 'suggestLoader', 'suggestText', 'تولید ایده هوشمند');
}


function renderCard(data, containerId, showActions = true) {
    const container = document.getElementById(containerId);
    const colorMap = { green: 'card-green', red: 'card-red', yellow: 'card-yellow' };
    const colorClass = colorMap[data.status_color] || 'card-yellow';
    
    const id = 'c' + Math.random().toString(36).substring(2, 10);
    const impl = Array.isArray(data.implementation) ? data.implementation.map(s => `<li>${s}</li>`).join('') : '<li>—</li>';
    
    const html = `
        <div id="${id}" class="linux-card ${colorClass} p-6">
            <div class="flex justify-between items-start">
                <h3 class="font-medium text-lg">${data.strategy_title || 'بدون عنوان'}</h3>
                <span class="badge">${data.category || 'عمومی'}</span>
            </div>
            <p class="text-zinc-400 text-sm mt-4">${data.analysis || 'تحلیل ارائه نشده.'}</p>
            <div class="grid grid-cols-2 gap-4 mt-6 text-xs">
                <div><span class="text-zinc-500">الگوهای جهانی:</span> ${data.global_benchmark || '—'}</div>
                <div><span class="text-zinc-500">تاثیر پیش‌بینی‌شده:</span> <span class="text-emerald-400">${data.predicted_impact || 'نامشخص'}</span></div>
            </div>
            <div class="mt-6 text-xs">
                <div class="text-zinc-500 mb-2">گام‌های اجرایی:</div>
                <ul class="list-disc text-zinc-400" style="margin-right:20px;">${impl}</ul>
            </div>
            ${showActions ? `
            <div class="mt-8 flex gap-6 text-xs">
                <button onclick="saveCardToHistory('${id}')" id="save-${id}" class="btn-text text-emerald-400">ذخیره در تاریخچه</button>
                <button onclick="deleteCard('${id}')" class="btn-text text-red-400">حذف کارت</button>
            </div>` : ''}
        </div>`;
    container.insertAdjacentHTML('afterbegin', html);
}

function deleteCard(id) { document.getElementById(id)?.remove(); }

async function saveCardToHistory(id) {
    const el = document.getElementById(id);
    if(!el) return;
    const data = {
        strategy_title: el.querySelector('h3').textContent,
        category: el.querySelector('.badge').textContent,
        analysis: el.querySelector('p').textContent,
        global_benchmark: el.querySelectorAll('.grid div')[0].textContent.split(': ')[1],
        predicted_impact: el.querySelectorAll('.grid div')[1].querySelector('span:last-child').textContent,
        implementation: Array.from(el.querySelectorAll('li')).map(li => li.textContent),
        status_color: el.classList.contains('card-green') ? 'green' : el.classList.contains('card-red') ? 'red' : 'yellow'
    };
    const f = new FormData();
    f.append('action', 'save_history');
    f.append('card', JSON.stringify(data));
    await fetch('', { method: 'POST', body: f });
    showNotification('کارت در تاریخچه ذخیره شد.');
    const btn = document.getElementById(`save-${id}`);
    if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; }
    loadHistory();
}

// --- DATA & HISTORY MANAGEMENT ---
async function loadBaseContexts() {
    try {
        const f = new FormData(); f.append('action', 'load_data');
        const r = await fetch('', { method: 'POST', body: f });
        const data = await r.json();
        const container = document.getElementById('baseContextsContainer');
        container.innerHTML = '';
        if (data && data.baseContexts) {
            data.baseContexts.forEach((item, i) => {
                container.innerHTML += `
                    <div class="linux-card p-4 flex justify-between items-center text-sm">
                        <div>
                            <div class="font-medium">${item.title}</div>
                            <div class="text-zinc-400 text-xs mt-1">${item.context.substring(0, 100)}...</div>
                        </div>
                        <button onclick="deleteBaseContext(${i})" class="btn-text text-red-400">حذف</button>
                    </div>`;
            });
        }
    } catch (e) {
        console.error("Error loading base contexts:", e);
    }
}

async function deleteBaseContext(i) {
    if (!confirm('آیا از حذف این کارت پایه مطمئن هستید؟')) return;
    const f = new FormData();
    f.append('action', 'delete_base_context');
    f.append('id', i);
    await fetch('', { method: 'POST', body: f });
    loadBaseContexts();
    showNotification('کارت پایه حذف شد.');
}

async function saveBaseContext() {
    const title = document.getElementById('base_title').value.trim();
    const context = document.getElementById('base_context').value.trim();
    
    if(!title || !context) return showNotification('عنوان و زمینه الزامی است.', true);

    const f = new FormData();
    f.append('action', 'save_base_context');
    f.append('id', document.getElementById('base_id').value);
    f.append('title', title);
    f.append('context', context);
    
    await fetch('', { method: 'POST', body: f });
    
    // Reset inputs
    document.getElementById('base_id').value = '';
    document.getElementById('base_title').value = '';
    document.getElementById('base_context').value = '';
    
    // Reload the list immediately
    loadBaseContexts();
    showNotification('کارت پایه صنایع چوب ذخیره شد.');
}

async function loadHistory() {
    try {
        const f = new FormData(); f.append('action', 'load_history');
        const r = await fetch('', { method: 'POST', body: f });
        const hist = await r.json();
        const container = document.getElementById('historyContainer');
        container.innerHTML = '';
        if(Array.isArray(hist)) hist.reverse().forEach(item => renderCard(item, 'historyContainer', false));
    } catch (e) {
        console.error("Error loading history:", e);
    }
}

// --- INITIALIZATION ---
document.addEventListener('DOMContentLoaded', () => {
    // Config Load
    const config = {"api_key":"sk-3v9Y7MxkLkqJyco3lMuysvChhdMeASiqn2GBzcLyDas7xJMB","base_url":"https:\/\/api.gapgpt.app\/v1","model_name":"gapgpt-qwen-3.5"};
    document.getElementById('api_key').value = config.api_key || '';
    document.getElementById('model_name').value = config.model_name || 'gemini-2.5-pro';
    
    // Fetch initial data
    loadBaseContexts();
    loadHistory();
});
</script>
<script>
/*
 * Universal Translator Engine v3
 * Persian -> English
 *
 * Private-project mode:
 * API key is intentionally stored in this JS file.
 *
 * Features:
 * - In-memory cache
 * - localStorage persistent cache
 * - IndexedDB persistent cache
 * - SHA-256 cache keys
 * - Batch translation
 * - Glossary / forced translations
 * - Dynamic DOM translation
 * - Placeholder/title/aria-label/alt
 * - English <-> Persian toggle without reload
 */

(() => {
  "use strict";

  const CONFIG = {
    apiKey: "sk-K0UYE8QlMeFGcQ14afhOIXGy4MM2OjXGoaVH34aQqC7t2w0H",
    endpoint: "https://api.gapgpt.app/v1/chat/completions",
    model: "gapgpt-qwen-3.6",

    defaultLanguage: "en",

    batchSize: 25,
    maxCharsPerRequest: 7000,

    localStorageKey: "universal_translator_cache_v3",
    languageKey: "universal_translator_language_v3",

    dbName: "UniversalTranslatorDB",
    dbVersion: 1,
    storeName: "translations",

    ignoredTags: new Set([
      "SCRIPT", "STYLE", "NOSCRIPT", "IFRAME",
      "OBJECT", "CODE", "PRE", "SVG", "CANVAS"
    ]),

    attributes: ["placeholder", "title", "aria-label", "alt"],

    glossary: {
      // Add your permanent terminology here:
      // "شبکه افکار": "Thought Network",
      // "آینه مجازی": "Virtual Mirror",
      // "مسئول فنی": "Technical Manager"
    }
  };

  const state = {
    memoryCache: new Map(),
    originalNodes: new WeakMap(),
    originalAttributes: new WeakMap(),
    translatedNodes: new Set(),
    translatedElements: new Set(),
    observer: null,
    processing: false,
    initialized: false,
    db: null
  };

  /* ---------------- Utilities ---------------- */

  const normalize = text =>
    String(text ?? "")
      .replace(/\u200c/g, " ")
      .replace(/\s+/g, " ")
      .trim();

  const isPersian = text =>
    /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF]/.test(text || "");

  const sleep = ms => new Promise(r => setTimeout(r, ms));

  function ignored(el) {
    if (!el || !el.tagName) return true;
    if (CONFIG.ignoredTags.has(el.tagName)) return true;
    if (el.closest?.("[data-no-translate]")) return true;
    if (el.id === "universal-translator-button") return true;
    return false;
  }

  /* ---------------- SHA-256 ---------------- */

  async function hash(text) {
    const data = new TextEncoder().encode(normalize(text));
    const digest = await crypto.subtle.digest("SHA-256", data);
    return [...new Uint8Array(digest)]
      .map(b => b.toString(16).padStart(2, "0"))
      .join("");
  }

  /* ---------------- IndexedDB ---------------- */

  function openDB() {
    return new Promise((resolve, reject) => {
      if (!("indexedDB" in window)) {
        resolve(null);
        return;
      }

      const request = indexedDB.open(CONFIG.dbName, CONFIG.dbVersion);

      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(CONFIG.storeName)) {
          db.createObjectStore(CONFIG.storeName, { keyPath: "hash" });
        }
      };

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  function idbGet(key) {
    if (!state.db) return Promise.resolve(null);

    return new Promise(resolve => {
      try {
        const tx = state.db.transaction(CONFIG.storeName, "readonly");
        const req = tx.objectStore(CONFIG.storeName).get(key);
        req.onsuccess = () => resolve(req.result?.translation || null);
        req.onerror = () => resolve(null);
      } catch {
        resolve(null);
      }
    });
  }

  function idbSet(key, source, translation) {
    if (!state.db) return Promise.resolve();

    return new Promise(resolve => {
      try {
        const tx = state.db.transaction(CONFIG.storeName, "readwrite");
        tx.objectStore(CONFIG.storeName).put({
          hash: key,
          source,
          translation,
          updatedAt: Date.now()
        });
        tx.oncomplete = () => resolve();
        tx.onerror = () => resolve();
      } catch {
        resolve();
      }
    });
  }

  /* ---------------- localStorage fallback/cache ---------------- */

  function loadLocalCache() {
    try {
      const raw = localStorage.getItem(CONFIG.localStorageKey);
      const parsed = raw ? JSON.parse(raw) : {};
      return parsed && typeof parsed === "object" ? parsed : {};
    } catch {
      return {};
    }
  }

  const localCache = loadLocalCache();

  function saveLocalCache() {
    try {
      localStorage.setItem(
        CONFIG.localStorageKey,
        JSON.stringify(localCache)
      );
    } catch (e) {
      console.warn("[Translator] localStorage write failed:", e);
    }
  }

  /* ---------------- Glossary ---------------- */

  function glossaryLookup(source) {
    const exact = normalize(source);

    if (Object.prototype.hasOwnProperty.call(CONFIG.glossary, exact)) {
      return CONFIG.glossary[exact];
    }

    return null;
  }

  /* ---------------- Cache ---------------- */

  async function cacheGet(source) {
    const text = normalize(source);
    if (!text) return null;

    if (state.memoryCache.has(text)) {
      return state.memoryCache.get(text);
    }

    const key = await hash(text);

    // localStorage first
    if (localCache[key]) {
      state.memoryCache.set(text, localCache[key]);
      return localCache[key];
    }

    // IndexedDB
    const idbValue = await idbGet(key);
    if (idbValue) {
      state.memoryCache.set(text, idbValue);
      localCache[key] = idbValue;
      saveLocalCache();
      return idbValue;
    }

    return null;
  }

  async function cacheSet(source, translation) {
    const text = normalize(source);
    const result = normalize(translation);
    if (!text || !result) return;

    const key = await hash(text);

    state.memoryCache.set(text, result);
    localCache[key] = result;
    saveLocalCache();

    await idbSet(key, text, result);
  }

  /* ---------------- DOM collection ---------------- */

  function collectTextNodes(root = document.body) {
    const result = [];
    if (!root) return result;

    const walker = document.createTreeWalker(
      root,
      NodeFilter.SHOW_TEXT,
      {
        acceptNode(node) {
          const parent = node.parentElement;
          if (!parent || ignored(parent)) {
            return NodeFilter.FILTER_REJECT;
          }

          const value = normalize(node.nodeValue);
          if (!value || !isPersian(value)) {
            return NodeFilter.FILTER_REJECT;
          }

          return NodeFilter.FILTER_ACCEPT;
        }
      }
    );

    let node;
    while ((node = walker.nextNode())) result.push(node);
    return result;
  }

  function collectAttributes(root = document.body) {
    const result = [];
    if (!root?.querySelectorAll) return result;

    for (const el of root.querySelectorAll("*")) {
      if (ignored(el)) continue;

      for (const attr of CONFIG.attributes) {
        if (!el.hasAttribute(attr)) continue;

        const value = normalize(el.getAttribute(attr));
        if (!value || !isPersian(value)) continue;

        result.push({ el, attr, value });
      }
    }

    return result;
  }

  /* ---------------- API ---------------- */

  function stripFences(text) {
    return String(text)
      .trim()
      .replace(/^```(?:json)?\s*/i, "")
      .replace(/\s*```$/i, "")
      .trim();
  }

  async function callAPI(texts) {
    if (!CONFIG.apiKey || CONFIG.apiKey === "YOUR_API_KEY") {
      throw new Error("API key is not configured in translation.js");
    }

    const response = await fetch(CONFIG.endpoint, {
      method: "POST",
      headers: {
        "Authorization": `Bearer ${CONFIG.apiKey}`,
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        model: CONFIG.model,
        messages: [
          {
            role: "system",
            content:
              "You are a professional Persian-to-English website translator. " +
              "Translate each item naturally and accurately. Preserve names, " +
              "numbers, punctuation, URLs, technical terms and placeholders. " +
              "Return ONLY a JSON array of strings, exactly one output per input."
          },
          {
            role: "user",
            content: JSON.stringify(texts)
          }
        ],
        temperature: 0.1,
        stream: false
      })
    });

    if (!response.ok) {
      const body = await response.text().catch(() => "");
      throw new Error(`API HTTP ${response.status}: ${body.slice(0, 500)}`);
    }

    const data = await response.json();

    const content =
      data?.choices?.[0]?.message?.content ??
      data?.choices?.[0]?.text ??
      "";

    if (!content) throw new Error("Empty translation response");

    let parsed;

    try {
      parsed = JSON.parse(stripFences(content));
    } catch {
      const match = content.match(/\[[\s\S]*\]/);
      if (!match) throw new Error("Invalid JSON array returned by model");
      parsed = JSON.parse(match[0]);
    }

    if (!Array.isArray(parsed)) {
      throw new Error("Translation response is not an array");
    }

    return parsed.map(x => String(x ?? ""));
  }

  /* ---------------- Batch translation ---------------- */

  async function translateTexts(texts) {
    const unique = [...new Set(texts.map(normalize).filter(Boolean))];
    const result = {};
    const missing = [];

    // Glossary + cache
    for (const text of unique) {
      const glossary = glossaryLookup(text);

      if (glossary) {
        result[text] = glossary;
        await cacheSet(text, glossary);
        continue;
      }

      const cached = await cacheGet(text);

      if (cached) {
        result[text] = cached;
      } else {
        missing.push(text);
      }
    }

    if (!missing.length) return result;

    const groups = [];
    let current = [];
    let chars = 0;

    for (const text of missing) {
      const size = text.length + 40;

      if (
        current.length >= CONFIG.batchSize ||
        (chars + size > CONFIG.maxCharsPerRequest && current.length)
      ) {
        groups.push(current);
        current = [];
        chars = 0;
      }

      current.push(text);
      chars += size;
    }

    if (current.length) groups.push(current);

    for (const group of groups) {
      const translated = await callAPI(group);

      for (let i = 0; i < group.length; i++) {
        const source = group[i];
        const target = normalize(translated[i] || source);

        result[source] = target;

        // Persist immediately.
        await cacheSet(source, target);
      }

      await sleep(10);
    }

    return result;
  }

  /* ---------------- Translation / restore ---------------- */

  async function translatePage() {
    if (state.processing) return;

    state.processing = true;
    setBusy(true);

    try {
      const nodes = collectTextNodes();
      const attrs = collectAttributes();

      const all = [
        ...nodes.map(n => normalize(n.nodeValue)),
        ...attrs.map(x => x.value)
      ];

      const translations = await translateTexts(all);

      for (const node of nodes) {
        if (!state.originalNodes.has(node)) {
          state.originalNodes.set(node, node.nodeValue);
        }

        const source = normalize(node.nodeValue);
        const target = translations[source];

        if (target && target !== source) {
          node.nodeValue = target;
          state.translatedNodes.add(node);
        }
      }

      for (const item of attrs) {
        let map = state.originalAttributes.get(item.el);

        if (!map) {
          map = {};
          state.originalAttributes.set(item.el, map);
        }

        if (!(item.attr in map)) {
          map[item.attr] = item.value;
        }

        const target = translations[item.value];

        if (target && target !== item.value) {
          item.el.setAttribute(item.attr, target);
          state.translatedElements.add(item.el);
        }
      }

      document.documentElement.lang = "en";
      document.documentElement.dir = "ltr";
      localStorage.setItem(CONFIG.languageKey, "en");
      updateButton("🇮🇷", "بازگشت به فارسی");
    } catch (error) {
      console.error("[Universal Translator]", error);
      showError(error.message);
    } finally {
      state.processing = false;
      setBusy(false);
    }
  }

  function restorePage() {
    for (const node of state.translatedNodes) {
      const original = state.originalNodes.get(node);
      if (original != null && node.isConnected) {
        node.nodeValue = original;
      }
    }

    for (const el of state.translatedElements) {
      const map = state.originalAttributes.get(el);
      if (!map || !el.isConnected) continue;

      for (const [attr, value] of Object.entries(map)) {
        el.setAttribute(attr, value);
      }
    }

    state.translatedNodes.clear();
    state.translatedElements.clear();

    document.documentElement.lang = "fa";
    document.documentElement.dir = "rtl";
    localStorage.setItem(CONFIG.languageKey, "fa");

    updateButton("🇬🇧", "Translate to English");
  }

  /* ---------------- Dynamic content ---------------- */

  function setupObserver() {
    if (state.observer || !document.body) return;

    let timer = null;

    state.observer = new MutationObserver(mutations => {
      const language =
        localStorage.getItem(CONFIG.languageKey) ||
        CONFIG.defaultLanguage;

      if (language !== "en") return;

      clearTimeout(timer);

      timer = setTimeout(async () => {
        if (state.processing) return;

        const nodes = [];

        for (const mutation of mutations) {
          for (const added of mutation.addedNodes) {
            if (added.nodeType === Node.TEXT_NODE) {
              if (isPersian(added.nodeValue)) nodes.push(added);
            } else if (added.nodeType === Node.ELEMENT_NODE) {
              nodes.push(...collectTextNodes(added));
            }
          }
        }

        if (!nodes.length) return;

        state.processing = true;
        setBusy(true);

        try {
          for (const node of nodes) {
            if (!state.originalNodes.has(node)) {
              state.originalNodes.set(node, node.nodeValue);
            }
          }

          const translations = await translateTexts(
            nodes.map(n => normalize(n.nodeValue))
          );

          for (const node of nodes) {
            const source = normalize(node.nodeValue);
            const target = translations[source];

            if (target && target !== source) {
              node.nodeValue = target;
              state.translatedNodes.add(node);
            }
          }
        } catch (error) {
          console.error("[Universal Translator observer]", error);
        } finally {
          state.processing = false;
          setBusy(false);
        }
      }, 300);
    });

    state.observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  /* ---------------- UI ---------------- */

  function createButton() {
    if (document.getElementById("universal-translator-button")) return;

    const button = document.createElement("button");
    button.id = "universal-translator-button";
    button.type = "button";
    button.textContent = "🇬🇧";
    button.title = "Translate to English";
    button.setAttribute("aria-label", "Translate to English");

    Object.assign(button.style, {
      position: "fixed",
      right: "16px",
      bottom: "16px",
      width: "42px",
      height: "42px",
      border: "0",
      borderRadius: "50%",
      background: "rgba(20,20,20,.92)",
      color: "#fff",
      cursor: "pointer",
      zIndex: "2147483647",
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      fontSize: "20px",
      lineHeight: "1",
      padding: "0",
      boxShadow: "0 4px 16px rgba(0,0,0,.25)",
      transition: "transform .15s ease, opacity .15s ease"
    });

    button.addEventListener("mouseenter", () => {
      button.style.transform = "scale(1.08)";
    });

    button.addEventListener("mouseleave", () => {
      button.style.transform = "scale(1)";
    });

    button.addEventListener("click", () => {
      const language =
        localStorage.getItem(CONFIG.languageKey) ||
        CONFIG.defaultLanguage;

      if (language === "en") {
        restorePage();
      } else {
        translatePage();
      }
    });

    document.body.appendChild(button);
  }

  function updateButton(icon, title) {
    const button =
      document.getElementById("universal-translator-button");

    if (!button) return;

    button.textContent = icon;
    button.title = title;
    button.setAttribute("aria-label", title);
  }

  function setBusy(busy) {
    const button =
      document.getElementById("universal-translator-button");

    if (!button) return;

    button.disabled = busy;
    button.style.opacity = busy ? ".55" : "1";
    button.style.cursor = busy ? "wait" : "pointer";
  }

  function showError(message) {
    document.getElementById("universal-translator-error")?.remove();

    const box = document.createElement("div");
    box.id = "universal-translator-error";

    Object.assign(box.style, {
      position: "fixed",
      right: "16px",
      bottom: "68px",
      maxWidth: "380px",
      padding: "10px 12px",
      borderRadius: "10px",
      background: "#2b1111",
      color: "#ffdede",
      font: "13px/1.5 Arial,sans-serif",
      zIndex: "2147483647",
      boxShadow: "0 5px 20px rgba(0,0,0,.25)"
    });

    box.textContent = "Translator: " + message;
    document.body.appendChild(box);

    setTimeout(() => box.remove(), 8000);
  }

  /* ---------------- Diagnostics ---------------- */

  async function cacheInfo() {
    const localEntries = Object.keys(localCache).length;
    let idbEntries = null;

    if (state.db) {
      idbEntries = await new Promise(resolve => {
        try {
          const tx = state.db.transaction(CONFIG.storeName, "readonly");
          const req = tx.objectStore(CONFIG.storeName).count();
          req.onsuccess = () => resolve(req.result);
          req.onerror = () => resolve(null);
        } catch {
          resolve(null);
        }
      });
    }

    const info = {
      memoryEntries: state.memoryCache.size,
      localStorageEntries: localEntries,
      indexedDBEntries: idbEntries,
      language:
        localStorage.getItem(CONFIG.languageKey) ||
        CONFIG.defaultLanguage
    };

    console.table(info);
    return info;
  }

  async function clearCache() {
    state.memoryCache.clear();

    for (const key of Object.keys(localCache)) {
      delete localCache[key];
    }

    localStorage.removeItem(CONFIG.localStorageKey);

    if (state.db) {
      await new Promise(resolve => {
        try {
          const tx = state.db.transaction(CONFIG.storeName, "readwrite");
          tx.objectStore(CONFIG.storeName).clear();
          tx.oncomplete = () => resolve();
          tx.onerror = () => resolve();
        } catch {
          resolve();
        }
      });
    }

    console.info("[Universal Translator] All translation caches cleared.");
  }

  /* ---------------- Init ---------------- */

  async function initialize() {
    if (state.initialized) return;
    state.initialized = true;

    try {
      state.db = await openDB();
    } catch {
      state.db = null;
    }

    createButton();
    setupObserver();

    const language =
      localStorage.getItem(CONFIG.languageKey) ||
      CONFIG.defaultLanguage;

    if (language === "fa") {
      updateButton("🇬🇧", "Translate to English");
      document.documentElement.lang = "fa";
      document.documentElement.dir = "rtl";
      return;
    }

    setTimeout(() => translatePage(), 150);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initialize, {
      once: true
    });
  } else {
    initialize();
  }

  window.UniversalTranslator = {
    translate: translatePage,
    restore: restorePage,
    cacheInfo,
    clearCache,
    config: CONFIG
  };
})();
</script>
</body>
</html>
