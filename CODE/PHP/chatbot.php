<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("config.php");
date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| SECURITY
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['action']) && $_GET['action'] === 'ask') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'reply' => 'Session expired. Please log in again.'
        ]);
        exit();
    }
    return;
}

$chatbotRole = $_SESSION['role'] ?? '';
$chatbotIsOwner = $chatbotRole === 'owner';
$chatbotIsEmployee = $chatbotRole === 'employee';

$chatbotCanInventory = (int)($_SESSION['can_inventory'] ?? 0) === 1;
$chatbotCanSales = (int)($_SESSION['can_sales'] ?? 0) === 1;
$chatbotCanSalesAnalytics = (int)($_SESSION['can_sales_analytics'] ?? 0) === 1;
$chatbotCanAccountsReceivable = (int)($_SESSION['can_accounts_receivable'] ?? 0) === 1;

/*
|--------------------------------------------------------------------------
| AJAX ENDPOINT
|--------------------------------------------------------------------------
*/
if (isset($_GET['action']) && $_GET['action'] === 'ask') {
    // Keep AJAX replies as clean JSON only. This prevents PHP warnings/notices from breaking the chatbot UI.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    mysqli_report(MYSQLI_REPORT_OFF);
    header('Content-Type: application/json; charset=utf-8');

    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => true,
                'reply' => 'Sorry, I had a system error while reading the data. Please check the chatbot.php file or try again after refreshing the page.'
            ], JSON_UNESCAPED_UNICODE);
        }
    });

    $question = trim($_POST['message'] ?? '');

    if ($question === '') {
        echo json_encode([
            'success' => true,
            'reply' => 'Please type your question first.'
        ]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | BASIC HELPERS
    |--------------------------------------------------------------------------
    */
    function cb_reply_json($reply, $context = null) {
        if ($context !== null && is_array($context)) {
            $_SESSION['nx_chatbot_context'] = array_merge($context, [
                'saved_at' => time()
            ]);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'success' => true,
            'reply' => $reply
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    function cb_get_last_context($maxAgeSeconds = 600) {
        if (empty($_SESSION['nx_chatbot_context']) || !is_array($_SESSION['nx_chatbot_context'])) {
            return null;
        }

        $context = $_SESSION['nx_chatbot_context'];

        if (empty($context['saved_at']) || (time() - (int)$context['saved_at']) > $maxAgeSeconds) {
            unset($_SESSION['nx_chatbot_context']);
            return null;
        }

        return $context;
    }

    function cb_normalize($text) {
        $text = mb_strtolower((string)$text, 'UTF-8');
        $text = str_replace(['’', "'", '`'], '', $text);
        $text = preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    function cb_tokenize($text) {
        $text = cb_normalize($text);
        if ($text === '') return [];
        $parts = explode(' ', $text);
        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') $tokens[] = $part;
        }
        return array_values(array_unique($tokens));
    }

    function cb_contains_any($text, array $needles) {
        $text = cb_normalize($text);
        foreach ($needles as $needle) {
            $needle = cb_normalize($needle);
            if ($needle !== '' && strpos($text, $needle) !== false) return true;
        }
        return false;
    }

    function cb_contains_phrase_or_word($text, array $needles) {
        $normalized = cb_normalize($text);
        $tokens = cb_tokenize($normalized);

        foreach ($needles as $needle) {
            $needle = cb_normalize($needle);
            if ($needle === '') continue;

            if (strpos($needle, ' ') !== false) {
                if (strpos($normalized, $needle) !== false) return true;
            } else {
                if (in_array($needle, $tokens, true)) return true;
            }
        }

        return false;
    }

    function cb_pick_reply(array $replies) {
        return $replies[array_rand($replies)];
    }

    function cb_money($amount) {
        return '₱' . number_format((float)$amount, 2);
    }

    function cb_percent($value) {
        return number_format((float)$value, 2) . '%';
    }

    function cb_table_exists($conn, $tableName) {
        $tableName = $conn->real_escape_string($tableName);
        $result = $conn->query("SHOW TABLES LIKE '{$tableName}'");
        return $result && $result->num_rows > 0;
    }

    function cb_open_module_reply($label, $url, $intro) {
        return $intro . "\n\n[OPEN_CONFIRM]|{$label}|{$url}";
    }

    /*
    |--------------------------------------------------------------------------
    | SMART SPELLING / GRAMMAR HELPERS
    |--------------------------------------------------------------------------
    */
    function cb_prepare_intent_text($text) {
        $text = cb_normalize($text);

        $phraseReplacements = [
            'lowstock' => 'low stock',
            'low stok' => 'low stock',
            'low stck' => 'low stock',
            'low stocks' => 'low stock',
            'outofstock' => 'out of stock',
            'out stock' => 'out of stock',
            'out stocks' => 'out of stock',
            'out off stock' => 'out of stock',
            'out-of-stock' => 'out of stock',
            'stock movment' => 'stock movement',
            'stock movements' => 'stock movement',
            'sales analitics' => 'sales analytics',
            'sales analytic' => 'sales analytics',
            'sale analytics' => 'sales analytics',
            'account receivable' => 'accounts receivable',
            'account recievable' => 'accounts receivable',
            'accounts recievable' => 'accounts receivable',
            'recievable summary' => 'receivable summary',
            'net profet' => 'net profit',
            'net profite' => 'net profit',
            'gross revanue' => 'gross revenue',
            'gross revinue' => 'gross revenue',
            'unpaid balace' => 'unpaid balance',
            'unpaid balances' => 'unpaid balance',
            'partialy paid' => 'partially paid',
            'partaly paid' => 'partially paid',
            'top sellng' => 'top selling',
            'top saling' => 'top selling',
            'pinaka mabenta' => 'top selling',
            'pinakamabenta' => 'top selling',
            'pinakamabentang' => 'top selling',
            'mga mabenta' => 'top selling products',
            'mabentang produkto' => 'top selling products',
            'slow moving' => 'slow moving',
            'no recent sale' => 'no recent sales',
            're stock' => 'restock',
            're-order' => 'reorder',
            're order' => 'reorder',
            'whole year' => 'this year',
            'entire year' => 'this year',
            'for the whole year' => 'this year',
            'for whole year' => 'this year',
            'yearly sales' => 'sales this year',
            'annual sales' => 'sales this year',

            // Filipino / Taglish natural how-to phrases
            'paano mag-add ng product' => 'how add product',
            'paano mag add ng product' => 'how add product',
            'paano mag-add product' => 'how add product',
            'paano mag add product' => 'how add product',
            'pano mag-add ng product' => 'how add product',
            'pano mag add ng product' => 'how add product',
            'paaano mag-add ng product' => 'how add product',
            'paano magdagdag ng product' => 'how add product',
            'paano magdagdag ng produkto' => 'how add product',
            'paano maglagay ng product' => 'how add product',
            'paano maglagay ng produkto' => 'how add product',
            'paano mag-create ng product' => 'how add product',
            'paano gumawa ng product' => 'how add product',
            'paano gumawa ng produkto' => 'how add product',

            'paano mag-record ng sale' => 'how record sale',
            'paano mag record ng sale' => 'how record sale',
            'paano mag-record ng sales' => 'how record sales',
            'paano mag record ng sales' => 'how record sales',
            'paano magbenta' => 'how record sale',
            'pano magbenta' => 'how record sale',
            'paano mag save ng sale' => 'how record sale',
            'paano mag-save ng sale' => 'how record sale',

            'paano mag-update ng stock' => 'how update stock',
            'paano mag update ng stock' => 'how update stock',
            'paano i-update ang stock' => 'how update stock',
            'paano dagdagan ang stock' => 'how update stock',
            'paano bawasan ang stock' => 'how update stock',
            'paano mag stock in' => 'how record stock in',
            'paano mag-stock in' => 'how record stock in',
            'paano mag stock out' => 'how record stock out',
            'paano mag-stock out' => 'how record stock out',

            'paano mag-manage ng categories' => 'how manage categories',
            'paano mag manage ng categories' => 'how manage categories',
            'paano mag-manage ng category' => 'how manage category',
            'paano mag manage ng category' => 'how manage category',
            'paano gumawa ng category' => 'how add category',
            'paano magdagdag ng category' => 'how add category',

            'paano mag-filter ng analytics' => 'how filter analytics',
            'paano mag filter ng analytics' => 'how filter analytics',
            'paano i-filter analytics' => 'how filter analytics',
            'paano mag-filter ng report' => 'how filter analytics',

            'paano makita ang receivables' => 'how view receivable',
            'paano makita ang receivable' => 'how view receivable',
            'paano tignan ang receivables' => 'how view receivable',
            'paano tingnan ang receivables' => 'how view receivable',
            'paano makita ang utang' => 'how view receivable',

            // Filipino inventory, sales, receivable, and navigation phrases
            'anong produkto ang low stock' => 'which product low stock',
            'ano ang low stock' => 'which product low stock',
            'anong produkto ang paubos' => 'which product low stock',
            'ano ang paubos' => 'which product low stock',
            'mga paubos na produkto' => 'low stock products',
            'mababa ang stock' => 'low stock',
            'konti na lang ang stock' => 'low stock',
            'kulang na stock' => 'low stock',
            'kulang ang stock' => 'low stock',
            'anong produkto ang ubos' => 'which product out of stock',
            'ano ang ubos na' => 'which product out of stock',
            'ubos na produkto' => 'out of stock product',
            'wala nang stock' => 'out of stock',
            'walang stock' => 'out of stock',
            'wlang stock' => 'out of stock',
            'wla stock' => 'out of stock',
            'magkano benta ngayon' => 'sales today',
            'magkano ang benta ngayon' => 'sales today',
            'magkano kita ngayon' => 'profit today',
            'sino may utang' => 'who unpaid balance',
            'sino ang may utang' => 'who unpaid balance',
            'may hindi pa bayad' => 'unpaid balance',
            'hindi pa bayad' => 'unpaid balance',
            'may balance pa' => 'unpaid balance',
            'magkano ang receivable' => 'total receivable balance',
            'magkano ang utang' => 'total receivable balance',
            'buksan inventory' => 'open inventory',
            'buksan sales' => 'open sales',
            'buksan analytics' => 'open analytics',
            'buksan receivables' => 'open receivables',
            'buksan settings' => 'open settings',
            'buksan dashboard' => 'open dashboard'
        ];

        $text = strtr($text, $phraseReplacements);

        $wordReplacements = [
            'wich' => 'which',
            'whch' => 'which',
            'whitch' => 'which',
            'wihch' => 'which',
            'wat' => 'what',
            'wht' => 'what',
            'whats' => 'what',
            'paano' => 'how',
            'pano' => 'how',
            'papaano' => 'how',
            'paaano' => 'how',
            'gano' => 'how',
            'ano' => 'what',
            'anong' => 'what',
            'alin' => 'which',
            'sino' => 'who',
            'kanino' => 'who',
            'magkano' => 'how much',
            'ilan' => 'how many',
            'may' => 'have',
            'ba' => '',
            'ng' => '',
            'ang' => '',
            'yung' => '',
            'iyong' => '',
            'mag-add' => 'add',
            'magadd' => 'add',
            'magdagdag' => 'add',
            'maglagay' => 'add',
            'gumawa' => 'add',
            'mag-create' => 'add',
            'magrecord' => 'record',
            'mag-record' => 'record',
            'magbenta' => 'record sale',
            'i-record' => 'record',
            'irecord' => 'record',
            'isave' => 'save',
            'i-save' => 'save',
            'magupdate' => 'update',
            'mag-update' => 'update',
            'iupdate' => 'update',
            'i-update' => 'update',
            'dagdagan' => 'stock in',
            'bawasan' => 'stock out',
            'tignan' => 'view',
            'tingnan' => 'view',
            'makita' => 'view',
            'buksan' => 'open',
            'punta' => 'open',
            'pumunta' => 'open',
            'howmuch' => 'how much',
            'howmany' => 'how many',
            'pls' => 'please',
            'plss' => 'please',
            'plsss' => 'please',
            'prodcut' => 'product',
            'prodcuts' => 'products',
            'prduct' => 'product',
            'prducts' => 'products',
            'pruduct' => 'product',
            'pruducts' => 'products',
            'prodak' => 'product',
            'produk' => 'product',
            'itemss' => 'items',
            'itms' => 'items',
            'stok' => 'stock',
            'stoks' => 'stocks',
            'stck' => 'stock',
            'stcok' => 'stock',
            'stockk' => 'stock',
            'salse' => 'sales',
            'saless' => 'sales',
            'salees' => 'sales',
            'seles' => 'sales',
            'sals' => 'sales',
            'benta' => 'sales',
            'profet' => 'profit',
            'profitt' => 'profit',
            'profitss' => 'profit',
            'revanue' => 'revenue',
            'revinue' => 'revenue',
            'cog' => 'cogs',
            'coggs' => 'cogs',
            'recievable' => 'receivable',
            'recievables' => 'receivable',
            'receivables' => 'receivable',
            'receveable' => 'receivable',
            'reciveable' => 'receivable',
            'balnce' => 'balance',
            'balanse' => 'balance',
            'balans' => 'balance',
            'balaces' => 'balances',
            'unpaids' => 'unpaid',
            'unpaidd' => 'unpaid',
            'overdues' => 'overdue',
            'catagory' => 'category',
            'catagories' => 'categories',
            'categoy' => 'category',
            'movment' => 'movement',
            'movments' => 'movements',
            'movemnt' => 'movement',
            'inventry' => 'inventory',
            'invetory' => 'inventory',
            'inventroy' => 'inventory',
            'inventori' => 'inventory',
            'analitics' => 'analytics',
            'analitycs' => 'analytics',
            'recomend' => 'recommend',
            'recomended' => 'recommended',
            'restok' => 'restock',
            'restoks' => 'restock',
            'restockk' => 'restock',
            'paobos' => 'paubos',
            'aler' => 'alert',
            'alrt' => 'alert',
            'notif' => 'notification',
            'produkto' => 'product',
            'produktoh' => 'product',
            'mabenta' => 'selling',
            'pinakamabenta' => 'top selling',
            'paubos' => 'low stock',
            'paobos' => 'low stock',
            'ubos' => 'out',
            'walang' => 'out',
            'wala' => 'out',
            'utang' => 'receivable',
            'balanse' => 'balance',
            'bayad' => 'paid',
            'kita' => 'profit',
            'ngayong' => 'this',
            'nakaraang' => 'last',
            'buwan' => 'month',
            'linggo' => 'week',
            'taon' => 'year',
            'yr' => 'year',
            'yer' => 'year',
            'yaer' => 'year',
            'taunan' => 'yearly'
        ];

        $tokens = explode(' ', $text);
        foreach ($tokens as $i => $token) {
            if (isset($wordReplacements[$token])) {
                $tokens[$i] = $wordReplacements[$token];
            }
        }

        $text = implode(' ', $tokens);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    function cb_word_match_score($token, $keyword) {
        $token = cb_normalize($token);
        $keyword = cb_normalize($keyword);

        if ($token === '' || $keyword === '') return 0;
        if ($token === $keyword) return 3;

        $maxLen = max(strlen($token), strlen($keyword));
        if ($maxLen < 4) return 0;

        $distance = levenshtein($token, $keyword);
        if ($distance <= 1) return 2;
        if ($maxLen >= 6 && $distance <= 2) return 1;

        return 0;
    }

    function cb_fuzzy_has_any_word($text, array $keywords) {
        $text = cb_prepare_intent_text($text);
        $tokens = cb_tokenize($text);

        foreach ($keywords as $keyword) {
            $keyword = cb_normalize($keyword);

            if (strpos($keyword, ' ') !== false) {
                if (strpos($text, $keyword) !== false) return true;
                continue;
            }

            foreach ($tokens as $token) {
                if (cb_word_match_score($token, $keyword) > 0) return true;
            }
        }

        return false;
    }

    function cb_fuzzy_has_all_groups($text, array $groups) {
        foreach ($groups as $group) {
            if (!cb_fuzzy_has_any_word($text, $group)) return false;
        }
        return true;
    }

    function cb_intent_match($text, array $phrases = [], array $keywordGroups = []) {
        $text = cb_prepare_intent_text($text);

        if (!empty($phrases) && cb_contains_any($text, $phrases)) {
            return true;
        }

        if (!empty($keywordGroups) && cb_fuzzy_has_all_groups($text, $keywordGroups)) {
            return true;
        }

        return false;
    }

    function cb_remove_question_words($text) {
        $text = cb_prepare_intent_text($text);
        $remove = [
            'what','is','are','my','the','of','for','in','on','do','i','have','any','show','give','me','please',
            'how','many','much','left','status','stock','reorder','level','product','products','item','items','current'
        ];
        $tokens = cb_tokenize($text);
        $kept = [];
        foreach ($tokens as $token) {
            if (!in_array($token, $remove, true)) $kept[] = $token;
        }
        return trim(implode(' ', $kept));
    }

    /*
    |--------------------------------------------------------------------------
    | DATE HELPERS
    |--------------------------------------------------------------------------
    */
    function cb_date_range_today() {
        return [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59'), 'Today'];
    }

    function cb_date_range_yesterday() {
        return [date('Y-m-d 00:00:00', strtotime('-1 day')), date('Y-m-d 23:59:59', strtotime('-1 day')), 'Yesterday'];
    }

    function cb_date_range_week() {
        $start = new DateTime();
        $start->modify('monday this week');
        $end = new DateTime();
        $end->modify('sunday this week');
        return [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59'), 'This Week'];
    }

    function cb_date_range_last_week() {
        $start = new DateTime();
        $start->modify('monday last week');
        $end = new DateTime();
        $end->modify('sunday last week');
        return [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59'), 'Last Week'];
    }

    function cb_date_range_month() {
        $start = new DateTime('first day of this month');
        $end = new DateTime('last day of this month');
        return [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59'), 'This Month'];
    }

    function cb_date_range_last_month() {
        $start = new DateTime('first day of last month');
        $end = new DateTime('last day of last month');
        return [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59'), 'Last Month'];
    }

    function cb_date_range_year() {
        // More stable than DateTime natural language parsing for some XAMPP/PHP versions.
        $year = (int)date('Y');
        return [
            $year . '-01-01 00:00:00',
            $year . '-12-31 23:59:59',
            'This Year'
        ];
    }

    function cb_date_range_last_year() {
        $year = (int)date('Y') - 1;
        return [
            $year . '-01-01 00:00:00',
            $year . '-12-31 23:59:59',
            'Last Year'
        ];
    }

    function cb_date_range_specific_year($year) {
        $year = (int)$year;
        if ($year < 2000 || $year > 2100) return null;
        return ["{$year}-01-01 00:00:00", "{$year}-12-31 23:59:59", "Year {$year}"];
    }

    function cb_try_year_range($text) {
        $normalized = cb_prepare_intent_text($text);

        if (preg_match('/(?:year|taon)\s*(20\d{2}|2100)|(?:for|in|ngayong|noong)\s*(20\d{2}|2100)/i', $normalized, $m)) {
            $year = !empty($m[1]) ? $m[1] : $m[2];
            return cb_date_range_specific_year($year);
        }

        if (preg_match('/\b(20\d{2}|2100)\b/', $normalized, $m) && cb_contains_any($normalized, ['sales', 'sale', 'profit', 'revenue', 'top', 'selling', 'product', 'category'])) {
            return cb_date_range_specific_year($m[1]);
        }

        return null;
    }

    function cb_try_custom_range($text) {
        if (preg_match('/from\s+([a-zA-Z]+\s+\d{1,2}|\d{4}\-\d{2}\-\d{2})\s+to\s+([a-zA-Z]+\s+\d{1,2}|\d{4}\-\d{2}\-\d{2})/i', $text, $m)) {
            $startText = $m[1];
            $endText = $m[2];

            $start = preg_match('/\d{4}\-\d{2}\-\d{2}/', $startText) ? strtotime($startText) : strtotime($startText . ' ' . date('Y'));
            $end = preg_match('/\d{4}\-\d{2}\-\d{2}/', $endText) ? strtotime($endText) : strtotime($endText . ' ' . date('Y'));

            if ($start && $end) {
                return [
                    date('Y-m-d 00:00:00', $start),
                    date('Y-m-d 23:59:59', $end),
                    date('M d, Y', $start) . ' to ' . date('M d, Y', $end)
                ];
            }
        }
        return null;
    }

    function cb_detect_date_range($text) {
        $normalized = cb_prepare_intent_text($text);

        $specificYear = cb_try_year_range($text);
        if ($specificYear) return $specificYear;

        if (cb_contains_any($normalized, ['today', 'ngayon'])) return cb_date_range_today();
        if (cb_contains_any($normalized, ['yesterday', 'kahapon'])) return cb_date_range_yesterday();
        if (cb_contains_any($normalized, ['last week', 'nakaraang linggo'])) return cb_date_range_last_week();
        if (cb_contains_any($normalized, ['this week', 'weekly', 'linggo na ito', 'week'])) return cb_date_range_week();
        if (cb_contains_any($normalized, ['last month', 'nakaraang buwan'])) return cb_date_range_last_month();
        if (cb_contains_any($normalized, ['this month', 'monthly', 'buwan na ito', 'month'])) return cb_date_range_month();
        if (cb_contains_any($normalized, ['last year', 'previous year', 'nakaraang taon'])) return cb_date_range_last_year();
        if (cb_contains_any($normalized, ['this year', 'yearly', 'annual', 'whole year', 'entire year', 'year', 'taon'])) return cb_date_range_year();

        return cb_try_custom_range($text);
    }

    /*
    |--------------------------------------------------------------------------
    | DATABASE HELPERS
    |--------------------------------------------------------------------------
    */
    function cb_get_summary($conn, $startDate, $endDate) {
        // Safe summary reader. It uses the selected date range only, so it can read March-to-present
        // or the whole 2026 year even if there are no January/February sales.
        $grossRevenue = 0;
        $cogs = 0;
        $transactions = 0;

        $start = $conn->real_escape_string($startDate);
        $end = $conn->real_escape_string($endDate);

        $sqlRevenue = "
            SELECT COALESCE(SUM(total_amount), 0) AS gross_revenue,
                   COUNT(*) AS total_transactions
            FROM sales
            WHERE sale_date >= '{$start}' AND sale_date <= '{$end}'
        ";
        $resultRevenue = $conn->query($sqlRevenue);
        if ($resultRevenue) {
            $row = $resultRevenue->fetch_assoc();
            $grossRevenue = (float)($row['gross_revenue'] ?? 0);
            $transactions = (int)($row['total_transactions'] ?? 0);
        }

        $sqlCogs = "
            SELECT COALESCE(SUM(p.cost_price * si.quantity), 0) AS total_cogs
            FROM sale_items si
            INNER JOIN sales s ON si.sale_id = s.id
            INNER JOIN products p ON si.product_id = p.id
            WHERE s.sale_date >= '{$start}' AND s.sale_date <= '{$end}'
        ";
        $resultCogs = $conn->query($sqlCogs);
        if ($resultCogs) {
            $row = $resultCogs->fetch_assoc();
            $cogs = (float)($row['total_cogs'] ?? 0);
        }

        return [
            'gross_revenue' => $grossRevenue,
            'cogs' => $cogs,
            'net_profit' => $grossRevenue - $cogs,
            'transactions' => $transactions
        ];
    }

    function cb_growth_from_summaries($currentRevenue, $previousRevenue) {
        if ($previousRevenue > 0) return (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
        if ($currentRevenue > 0) return 100;
        return 0;
    }

    function cb_compare_label($current, $previous) {
        if ($current > $previous) return 'increased';
        if ($current < $previous) return 'decreased';
        return 'stayed the same';
    }

    function cb_get_low_stock($conn, $limit = 10) {
        $items = [];
        $stmt = $conn->prepare("SELECT product_name, product_code, stock_quantity, reorder_level, on_order_level FROM products WHERE is_active = 1 AND stock_quantity <= reorder_level AND stock_quantity > 0 ORDER BY stock_quantity ASC, product_name ASC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_out_of_stock($conn, $limit = 10) {
        $items = [];
        $stmt = $conn->prepare("SELECT product_name, product_code, stock_quantity, on_order_level FROM products WHERE is_active = 1 AND stock_quantity <= 0 ORDER BY product_name ASC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_nearing_depletion($conn, $limit = 10) {
        $items = [];
        $stmt = $conn->prepare("SELECT product_name, product_code, stock_quantity, reorder_level FROM products WHERE is_active = 1 AND stock_quantity > reorder_level AND stock_quantity <= (reorder_level + 3) ORDER BY stock_quantity ASC, product_name ASC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_recent_stock_movements($conn, $limit = 5) {
        $items = [];
        $stmt = $conn->prepare("SELECT sm.movement_type, sm.quantity, sm.created_at, sm.remarks, p.product_name, p.product_code FROM stock_movements sm INNER JOIN products p ON sm.product_id = p.id ORDER BY sm.created_at DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_recent_products($conn, $limit = 5) {
        $items = [];
        $stmt = $conn->prepare("SELECT product_name, product_code, created_at FROM products ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_product_stock_by_name($conn, $name) {
        $items = [];
        $stmt = $conn->prepare("SELECT product_name, product_code, stock_quantity, reorder_level, on_order_level FROM products WHERE is_active = 1 AND product_name LIKE ? ORDER BY product_name ASC LIMIT 5");
        $search = '%' . $name . '%';
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_find_best_product_match($conn, $question) {
        $questionNorm = cb_prepare_intent_text($question);
        $questionTokens = cb_tokenize($questionNorm);

        $result = $conn->query("SELECT id, product_name, product_code, stock_quantity, reorder_level, on_order_level FROM products WHERE is_active = 1 ORDER BY product_name ASC");
        if (!$result) return null;

        $best = null;
        $bestScore = 0;

        while ($row = $result->fetch_assoc()) {
            $nameNorm = cb_prepare_intent_text($row['product_name']);
            $nameTokens = cb_tokenize($nameNorm);

            if ($nameNorm !== '' && strpos($questionNorm, $nameNorm) !== false) return $row;

            $score = 0;
            foreach ($nameTokens as $nameToken) {
                foreach ($questionTokens as $questionToken) {
                    $score += cb_word_match_score($questionToken, $nameToken);
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        return $bestScore >= 3 ? $best : null;
    }

    function cb_get_top_products_by_qty($conn, $startDate, $endDate, $limit = 5) {
        $items = [];
        $stmt = $conn->prepare("SELECT p.product_name, COALESCE(SUM(si.quantity), 0) AS qty_sold FROM sale_items si INNER JOIN sales s ON si.sale_id = s.id INNER JOIN products p ON si.product_id = p.id WHERE s.sale_date BETWEEN ? AND ? GROUP BY p.id, p.product_name ORDER BY qty_sold DESC, p.product_name ASC LIMIT ?");
        $stmt->bind_param("ssi", $startDate, $endDate, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_top_products_by_revenue($conn, $startDate, $endDate, $limit = 5) {
        $items = [];
        $stmt = $conn->prepare("SELECT p.product_name, COALESCE(SUM(si.quantity * si.unit_price), 0) AS revenue FROM sale_items si INNER JOIN sales s ON si.sale_id = s.id INNER JOIN products p ON si.product_id = p.id WHERE s.sale_date BETWEEN ? AND ? GROUP BY p.id, p.product_name ORDER BY revenue DESC, p.product_name ASC LIMIT ?");
        $stmt->bind_param("ssi", $startDate, $endDate, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_slow_products($conn, $startDate, $endDate, $limit = 5) {
        $items = [];
        $stmt = $conn->prepare("SELECT p.product_name, COALESCE(SUM(CASE WHEN s.sale_date BETWEEN ? AND ? THEN si.quantity ELSE 0 END), 0) AS qty_sold FROM products p LEFT JOIN sale_items si ON p.id = si.product_id LEFT JOIN sales s ON si.sale_id = s.id WHERE p.is_active = 1 GROUP BY p.id, p.product_name ORDER BY qty_sold ASC, p.product_name ASC LIMIT ?");
        $stmt->bind_param("ssi", $startDate, $endDate, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_no_recent_sales($conn, $days = 30, $limit = 10) {
        $items = [];
        $stmt = $conn->prepare("SELECT p.product_name FROM products p LEFT JOIN sale_items si ON p.id = si.product_id LEFT JOIN sales s ON si.sale_id = s.id AND s.sale_date >= (NOW() - INTERVAL ? DAY) WHERE p.is_active = 1 GROUP BY p.id, p.product_name HAVING COUNT(s.id) = 0 ORDER BY p.product_name ASC LIMIT ?");
        $stmt->bind_param("ii", $days, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_category_units($conn, $startDate, $endDate) {
        $items = [];
        $stmt = $conn->prepare("SELECT c.category_name, COALESCE(SUM(si.quantity), 0) AS units_sold FROM sale_items si INNER JOIN sales s ON si.sale_id = s.id INNER JOIN products p ON si.product_id = p.id INNER JOIN categories c ON p.category_id = c.id WHERE s.sale_date BETWEEN ? AND ? GROUP BY c.id, c.category_name ORDER BY units_sold DESC, c.category_name ASC");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_category_revenue($conn, $startDate, $endDate) {
        $items = [];
        $stmt = $conn->prepare("SELECT c.category_name, COALESCE(SUM(si.quantity * si.unit_price), 0) AS revenue FROM sale_items si INNER JOIN sales s ON si.sale_id = s.id INNER JOIN products p ON si.product_id = p.id INNER JOIN categories c ON p.category_id = c.id WHERE s.sale_date BETWEEN ? AND ? GROUP BY c.id, c.category_name ORDER BY revenue DESC, c.category_name ASC");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_sales_drop_alert($conn) {
        [$curStart, $curEnd] = cb_date_range_week();
        [$prevStart, $prevEnd] = cb_date_range_last_week();
        $current = cb_get_summary($conn, $curStart, $curEnd);
        $previous = cb_get_summary($conn, $prevStart, $prevEnd);

        if ($current['gross_revenue'] < $previous['gross_revenue']) {
            $diff = $previous['gross_revenue'] - $current['gross_revenue'];
            return 'Sales dropped this week by ' . cb_money($diff) . ' compared to last week.';
        }
        return null;
    }

    function cb_get_unusual_stock_movements($conn, $limit = 5) {
        $items = [];
        $stmt = $conn->prepare("SELECT sm.movement_type, sm.quantity, sm.created_at, p.product_name FROM stock_movements sm INNER JOIN products p ON sm.product_id = p.id WHERE sm.quantity >= 20 ORDER BY sm.created_at DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_receivable_summary($conn) {
        if (!cb_table_exists($conn, 'accounts_receivable')) return null;

        $summary = [
            'total_receivables' => 0,
            'total_balance_due' => 0,
            'overdue_count' => 0,
            'unpaid_count' => 0,
            'partial_count' => 0
        ];

        $result = $conn->query("SELECT COUNT(*) AS total_receivables, COALESCE(SUM(balance_due), 0) AS total_balance_due, SUM(CASE WHEN due_date IS NOT NULL AND due_date <> '' AND due_date < CURDATE() AND balance_due > 0 THEN 1 ELSE 0 END) AS overdue_count, SUM(CASE WHEN balance_due > 0 AND (amount_paid IS NULL OR amount_paid <= 0) AND NOT (due_date IS NOT NULL AND due_date <> '' AND due_date < CURDATE()) THEN 1 ELSE 0 END) AS unpaid_count, SUM(CASE WHEN amount_paid > 0 AND balance_due > 0 AND NOT (due_date IS NOT NULL AND due_date <> '' AND due_date < CURDATE()) THEN 1 ELSE 0 END) AS partial_count FROM accounts_receivable");
        if ($result) $summary = $result->fetch_assoc();

        return $summary;
    }

    function cb_get_unpaid_receivables($conn, $limit = 10) {
        $items = [];
        if (!cb_table_exists($conn, 'accounts_receivable')) return $items;

        $stmt = $conn->prepare("SELECT ar.balance_due, ar.amount_paid, ar.due_date, c.customer_name, s.sales_no FROM accounts_receivable ar INNER JOIN customers c ON ar.customer_id = c.id INNER JOIN sales s ON ar.sale_id = s.id WHERE ar.balance_due > 0 ORDER BY ar.due_date ASC, ar.balance_due DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_overdue_receivables($conn, $limit = 10) {
        $items = [];
        if (!cb_table_exists($conn, 'accounts_receivable')) return $items;

        $stmt = $conn->prepare("SELECT ar.balance_due, ar.due_date, c.customer_name, s.sales_no FROM accounts_receivable ar INNER JOIN customers c ON ar.customer_id = c.id INNER JOIN sales s ON ar.sale_id = s.id WHERE ar.balance_due > 0 AND ar.due_date IS NOT NULL AND ar.due_date <> '' AND ar.due_date < CURDATE() ORDER BY ar.due_date ASC, ar.balance_due DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    function cb_get_followup_priority($conn, $limit = 5) {
        $items = [];
        if (!cb_table_exists($conn, 'accounts_receivable')) return $items;

        $stmt = $conn->prepare("SELECT c.customer_name, s.sales_no, ar.balance_due, ar.due_date, CASE WHEN ar.balance_due <= 0 THEN 'Paid' WHEN ar.due_date IS NOT NULL AND ar.due_date <> '' AND ar.due_date < CURDATE() AND ar.balance_due > 0 THEN 'Overdue' WHEN ar.amount_paid > 0 AND ar.balance_due > 0 THEN 'Partially Paid' ELSE 'Unpaid' END AS live_status FROM accounts_receivable ar INNER JOIN customers c ON ar.customer_id = c.id INNER JOIN sales s ON ar.sale_id = s.id WHERE ar.balance_due > 0 ORDER BY CASE WHEN ar.due_date IS NOT NULL AND ar.due_date <> '' AND ar.due_date < CURDATE() AND ar.balance_due > 0 THEN 1 WHEN ar.amount_paid > 0 AND ar.balance_due > 0 THEN 2 ELSE 3 END, ar.due_date ASC, ar.balance_due DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();
        return $items;
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZED INPUT AND INTENTS
    |--------------------------------------------------------------------------
    */
    $normalized = cb_normalize($question);
    $smartText = cb_prepare_intent_text($question);
    $range = cb_detect_date_range($question);
    $lastContext = cb_get_last_context();

    $isCorrectionQuestion = cb_contains_any($smartText, [
        'i mean',
        'i meant',
        'no i mean',
        'sorry i mean',
        'what i mean',
        'ang ibig ko sabihin',
        'ibig kong sabihin',
        'yung ibig ko sabihin',
        'ay hindi',
        'hindi',
        'mali'
    ]);

    $isFollowUpQuestion = cb_contains_any($smartText, [
        'how about',
        'what about',
        'how about naman',
        'what about naman',
        'paano naman',
        'pano naman',
        'eh yung',
        'e yung',
        'yung last month',
        'yung this month',
        'yung today',
        'naman'
    ]);

    $isGreeting = cb_contains_phrase_or_word($smartText, ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'kamusta', 'kumusta']);
    $isThanks = cb_contains_phrase_or_word($smartText, ['thank you', 'thanks', 'salamat', 'ty']);
    $isBotIdentity = cb_intent_match($smartText, ['who are you', 'what can you do', 'help me', 'help'], [['help', 'assist', 'do']]);
    $isPossibleQuestionsQuestion = cb_intent_match($smartText, ['possible questions', 'sample questions', 'example questions', 'what questions can i ask', 'list questions', 'list of questions', 'ano pwede itanong', 'ano ang pwede itanong', 'anong pwede itanong', 'mga pwedeng itanong', 'help me ask'], [['question','questions','ask','tanong','itanong'], ['possible','sample','example','list','pwede','pwedeng','what']]);

    $isOpenDashboard = cb_intent_match($smartText, ['open dashboard', 'go to dashboard', 'buksan dashboard'], [['open','go','buksan'], ['dashboard']]);
    $isOpenAbout = cb_intent_match($smartText, ['open about us', 'go to about us', 'buksan about us'], [['open','go','buksan'], ['about']]);
    $isOpenSettings = cb_intent_match($smartText, ['open settings', 'go to settings', 'buksan settings'], [['open','go','buksan'], ['settings']]);
    $isOpenInventory = cb_intent_match($smartText, ['open inventory', 'open inventory management', 'go to inventory', 'buksan inventory'], [['open','go','buksan'], ['inventory']]);
    $isOpenAnalytics = cb_intent_match($smartText, ['open sales analytics', 'open analytics', 'go to analytics', 'buksan analytics'], [['open','go','buksan'], ['analytics']]);
    $isOpenSales = cb_intent_match($smartText, ['open sales recording', 'go to sales recording', 'open sales page', 'open sales', 'buksan sales'], [['open','go','buksan'], ['sales']]) && !cb_contains_any($smartText, ['analytics']);
    $isOpenReceivables = cb_intent_match($smartText, ['open receivables', 'open accounts receivable', 'go to receivables', 'buksan receivables'], [['open','go','buksan'], ['receivable']]);

    $isAlertQuestion = cb_intent_match($smartText, ['what alerts do i have today', 'alerts today', 'any alerts today', 'urgent issues', 'urgent inventory issue', 'notifications today'], [['alert','alerts','urgent','notification'], ['today','current','now']]);

    $isLowStockQuestion = cb_intent_match($smartText, ['which items are low stock', 'which products are low stock', 'low stock', 'anong low stock', 'alin ang low stock', 'paubos', 'kulang stock', 'konti stock', 'anong produkto ang low stock', 'ano ang low stock', 'ano ang paubos', 'anong paubos', 'anong produkto ang paubos', 'mga paubos na produkto', 'mababa ang stock', 'konti na lang ang stock', 'kulang na stock', 'kulang ang stock'], [['low','paubos','kulang','konti','mababa'], ['stock','item','items','product','products','produkto']]);
    $isOutOfStockQuestion = cb_intent_match($smartText, ['out of stock', 'do i have any out of stock products', 'walang stock', 'ubos na stock', 'wala nang stock', 'ubos stock', 'ano ang ubos na', 'anong produkto ang ubos', 'ubos na produkto', 'wla stock', 'wlang stock'], [['out','ubos','walang','wala'], ['stock','product','products','item','items','produkto']]);
    $isNearingDepletionQuestion = cb_intent_match($smartText, ['nearing depletion', 'products nearing depletion', 'almost low stock', 'almost out of stock'], [['nearing','almost','malapit'], ['depletion','low','out','ubos','stock']]);
    $isRecentStockMovementQuestion = cb_intent_match($smartText, ['recent stock movement', 'show recent stock movement', 'stock history', 'latest stock movement'], [['recent','latest','show','history'], ['stock'], ['movement','history']]);

    $isSpecificProductStockQuestion =
        preg_match('/stock of (.+)$/i', $smartText) ||
        preg_match('/reorder level of (.+)$/i', $smartText) ||
        preg_match('/status of (.+)$/i', $smartText) ||
        preg_match('/how many left of (.+)$/i', $smartText) ||
        preg_match('/stock for (.+)$/i', $smartText) ||
        cb_intent_match($smartText, [], [['stock','reorder','status','left'], ['product','item']]);

    $isSalesSummaryQuestion = cb_intent_match($smartText, ['what are my sales', 'what is my sales', 'sales today', 'sales this week', 'sales this month', 'sales this year', 'show my sales', 'sales summary', 'show my profit', 'today sales', 'weekly sales', 'monthly sales', 'yearly sales', 'annual sales', 'whole year sales', 'sales for the whole year', 'sales for whole year', 'sales for this year', 'how about the sales for the whole year', 'magkano benta ngayon', 'magkano ang benta ngayon', 'benta ngayon', 'benta today', 'benta this week', 'benta this month', 'benta this year', 'benta buong taon', 'kita ngayon', 'kita today', 'magkano kita ngayon'], [['sales','sale','profit','benta','kita','revenue'], ['today','week','month','year','annual','yearly','summary','ngayon','linggo','buwan','taon']]);
    $isGrossRevenueQuestion = cb_intent_match($smartText, ['gross revenue'], [['gross'], ['revenue']]) && !cb_contains_any($smartText, ['meaning', 'explain', 'what is gross revenue']);
    $isNetProfitValueQuestion = cb_intent_match($smartText, ['net profit today', 'my net profit', 'show net profit'], [['net'], ['profit'], ['today','week','month','show','my']]) && !cb_contains_any($smartText, ['meaning', 'explain']);

    $isCompareTodayVsYesterday = cb_intent_match($smartText, ['compare today vs yesterday', 'today vs yesterday'], [['today'], ['yesterday']]);
    $isCompareWeekVsLastWeek = cb_intent_match($smartText, ['compare this week vs last week', 'this week vs last week', 'why did sales drop this week'], [['week'], ['last','drop','compare']]);
    $isCompareMonthVsLastMonth = cb_intent_match($smartText, ['compare this month to last month', 'compare this month vs last month', 'this month vs last month'], [['month'], ['last','compare']]);

    $isTopProductsQuestion = cb_intent_match($smartText, ['top 5 products', 'top products', 'top selling products', 'highest selling product', 'best selling products', 'best products', 'most sold products', 'last month top 5 products', 'top 5 products last month',
        'top 5 products this year',
        'top products this year',
        'top selling products this year',
        'top 5 products for the whole year', 'pinakamabenta', 'pinaka mabenta', 'pinakamabentang produkto', 'pinaka mabentang produkto', 'mabentang produkto', 'mga mabenta', 'top na produkto', 'top products noong nakaraang buwan', 'pinakamabenta last month', 'pinakamabenta ngayong buwan', 'pinakamabenta ngayon'], [['top','highest','best','pinakamabenta','mabenta'], ['product','products','selling','item','items','produkto']]);
    $isRevenueContributionQuestion = cb_intent_match($smartText, ['rank products by revenue', 'revenue contribution'], [['revenue'], ['product','rank','contribution']]);
    $isSlowMovingQuestion = cb_intent_match($smartText, ['slow moving products', 'products are not selling well', 'not selling well'], [['slow','not','weak'], ['selling','moving','product']]);
    $isNoRecentSalesQuestion = cb_intent_match($smartText, ['no recent sales', 'products with no recent sales'], [['no','without'], ['recent'], ['sales']]);

    $isTopCategoryQuestion = cb_intent_match($smartText, ['which category sold the most', 'top selling category', 'best category'], [['top','best','most'], ['category']]);
    $isWeakestCategoryQuestion = cb_intent_match($smartText, ['lowest performing category', 'weakest category', 'which category is the weakest'], [['weakest','lowest','weak'], ['category']]);
    $isCompareCategoriesQuestion = cb_intent_match($smartText, ['compare categories', 'categories by units sold'], [['compare','units'], ['category','categories']]);
    $isCompareCategoryRevenueQuestion = cb_intent_match($smartText, ['compare categories by revenue', 'category revenue contribution'], [['category','categories'], ['revenue']]);

    $isPromoteRecommendationQuestion = cb_intent_match($smartText, ['which product should i promote', 'recommend which products to promote', 'marketing focus'], [['promote','marketing'], ['product','products']]);
    $isRestockRecommendationQuestion = cb_intent_match($smartText, ['recommend which products to restock', 'which products should i restock', 'what items are urgent to reorder', 'restock first'], [['restock','reorder','urgent'], ['product','products','items','stock']]);
    $isCategoryAttentionQuestion = cb_intent_match($smartText, ['what categories need attention', 'recommend what categories need attention'], [['category','categories'], ['attention','recommend']]);

    $isOverdueReceivableQuestion = cb_intent_match($smartText, ['overdue receivable', 'do i have overdue receivables', 'overdue payments'], [['overdue'], ['receivable','payment','balance']]);
    $isReceivableSummaryQuestion = cb_intent_match($smartText, ['how many unpaid balances do i have', 'unpaid balances', 'receivable summary', 'total accounts receivable', 'total receivable balance', 'how much is my total accounts receivable', 'sino may utang', 'sino ang may utang', 'may unpaid ba', 'may hindi pa bayad', 'hindi pa bayad', 'may balance pa', 'may balanse pa', 'magkano ang receivable', 'magkano ang utang'], [['receivable','unpaid','balance','utang'], ['summary','total','many','much','ilan','magkano']]);
    $isUnpaidBalancesQuestion = cb_intent_match($smartText, ['who has unpaid balances', 'show unpaid balances', 'unpaid accounts'], [['unpaid'], ['balance','account','who','customer']]);
    $isFollowupPriorityQuestion = cb_intent_match($smartText, ['which customer account should i follow up first', 'follow up priority', 'follow-up priorities'], [['follow','priority'], ['customer','account','receivable']]);

    $isHowAddProduct = cb_intent_match($smartText, ['how do i add a new product', 'how to add a new product', 'how do i add product'], [['add'], ['product']]);
    $isHowRecordSale = cb_intent_match($smartText, ['how do i record a sale', 'how to record a sale'], [['record','add'], ['sale','sales']]);
    $isHowUpdateStock = cb_intent_match($smartText, ['how do i update stock', 'how to update stock', 'how do i record a stock out', 'how do i record stock in'], [['update','record'], ['stock']]);
    $isHowManageCategories = cb_intent_match($smartText, ['how do i manage categories', 'how to manage categories'], [['manage','add'], ['category','categories']]);
    $isHowFilterAnalytics = cb_intent_match($smartText, ['how do i filter analytics', 'how to filter analytics'], [['filter'], ['analytics']]);
    $isHowViewReceivables = cb_intent_match($smartText, ['how do i view receivables', 'how to view receivables'], [['view','see'], ['receivable']]);

    $isCogsQuestion = cb_intent_match($smartText, ['what is cogs', 'explain cogs', 'meaning of cogs', 'cost of goods sold'], [['cogs','cost'], ['meaning','explain','what']]);
    $isNetProfitQuestion = cb_intent_match($smartText, ['what is net profit', 'explain net profit', 'meaning of net profit'], [['net'], ['profit'], ['meaning','explain','what']]);
    $isReorderLevelQuestion = cb_intent_match($smartText, ['what is reorder level', 'explain reorder level', 'meaning of reorder level'], [['reorder'], ['level'], ['meaning','explain','what']]);
    $isSalesGrowthQuestion = cb_intent_match($smartText, ['what is sales growth', 'explain sales growth'], [['sales'], ['growth'], ['meaning','explain','what']]);
    $isTopSellingMeaningQuestion = cb_intent_match($smartText, ['what is top selling products', 'explain top selling products'], [['top'], ['selling'], ['meaning','explain','what']]);
    $isStockMovementMeaningQuestion = cb_intent_match($smartText, ['what is stock movement', 'explain stock movement'], [['stock'], ['movement'], ['meaning','explain','what']]);
    $isReceivableMeaningQuestion = cb_intent_match($smartText, ['what is accounts receivable', 'explain accounts receivable'], [['receivable'], ['meaning','explain','what']]);
    $isGrossRevenueMeaningQuestion = cb_intent_match($smartText, ['what is gross revenue', 'explain gross revenue', 'meaning of gross revenue'], [['gross'], ['revenue'], ['meaning','explain','what']]);

    /*
    |--------------------------------------------------------------------------
    | CONVERSATION CONTEXT + FOLLOW-UP HANDLING
    |--------------------------------------------------------------------------
    | This helps with natural follow-ups like:
    | "how about last month?"
    | "i mean last month top 5 products"
    | "paano naman last month?"
    */
    if ($isCorrectionQuestion || $isFollowUpQuestion) {
        if ($isTopProductsQuestion) {
            $isSalesSummaryQuestion = false;
            $isCompareTodayVsYesterday = false;
            $isCompareWeekVsLastWeek = false;
            $isCompareMonthVsLastMonth = false;
        }

        if ($lastContext && !$isTopProductsQuestion && !$isRevenueContributionQuestion && !$isSlowMovingQuestion && !$isNoRecentSalesQuestion && !$isLowStockQuestion && !$isOutOfStockQuestion && !$isSalesSummaryQuestion) {
            $lastTopic = $lastContext['topic'] ?? '';

            if ($lastTopic === 'top_products') {
                $isTopProductsQuestion = true;
                $isCompareTodayVsYesterday = false;
                $isCompareWeekVsLastWeek = false;
                $isCompareMonthVsLastMonth = false;
            } elseif ($lastTopic === 'revenue_products') {
                $isRevenueContributionQuestion = true;
                $isCompareTodayVsYesterday = false;
                $isCompareWeekVsLastWeek = false;
                $isCompareMonthVsLastMonth = false;
            } elseif ($lastTopic === 'sales_summary') {
                $isSalesSummaryQuestion = true;
            } elseif ($lastTopic === 'inventory_low_stock') {
                $isLowStockQuestion = true;
            } elseif ($lastTopic === 'inventory_out_stock') {
                $isOutOfStockQuestion = true;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INTENT PRIORITY FIX
    |--------------------------------------------------------------------------
    | Product questions should not be answered as month/week comparison.
    */
    if (
        $isTopProductsQuestion ||
        $isRevenueContributionQuestion ||
        $isSlowMovingQuestion ||
        $isNoRecentSalesQuestion ||
        $isPromoteRecommendationQuestion ||
        $isRestockRecommendationQuestion ||
        $isCategoryAttentionQuestion
    ) {
        $isCompareTodayVsYesterday = false;
        $isCompareWeekVsLastWeek = false;
        $isCompareMonthVsLastMonth = false;
        $isSalesSummaryQuestion = false;
    }

    /*
    |--------------------------------------------------------------------------
    | ORDERED ROUTER
    |--------------------------------------------------------------------------
    */
    if ($isGreeting) {
        cb_reply_json(cb_pick_reply([
            'Hi! How may I help you today?',
            'Hello! What would you like to check today?',
            'Hi there! You can ask me about inventory, sales, receivables, alerts, or system help.',
            'Hello! I can help you check low stock items, sales today, overdue receivables, or how to use the system.'
        ]));
    }

    if ($isThanks) {
        cb_reply_json(cb_pick_reply([
            'You’re welcome! Anything else you want to check?',
            'No problem! I’m here if you need more help.',
            'Glad to help!'
        ]));
    }

    if ($isBotIdentity || $isPossibleQuestionsQuestion) {
        $reply = "I’m your NextGen Assistant. Here are possible questions you can ask me, including sample answers:

" .
            "Inventory:
" .
            "Q: Which items are low stock?
" .
            "A: I will list the products that reached or went below their reorder level.
" .
            "Q: Do I have out-of-stock products?
" .
            "A: I will list products with 0 stock.
" .
            "Q: Paano mag-add ng product?
" .
            "A: I will guide you to open Inventory Management and use + Add Product.

" .
            "Sales and Analytics:
" .
            "Q: What are my sales today?
" .
            "A: I will show gross revenue, COGS, net profit, and total transactions for today.
" .
            "Q: How about the sales for the whole year?
" .
            "A: I will show the same sales summary for this year.
" .
            "Q: What are my top 5 products this year?
" .
            "A: I will rank your top products by quantity sold for this year.
" .
            "Q: Compare this month vs last month.
" .
            "A: I will compare revenue and sales growth.

" .
            "Receivables:
" .
            "Q: Who has unpaid balances?
" .
            "A: I will list customers with remaining balances.
" .
            "Q: Sino may utang?
" .
            "A: I will show unpaid or partially paid customer accounts.
" .
            "Q: Which customer account should I follow up first?
" .
            "A: I will prioritize overdue and unpaid receivables.

" .
            "Navigation:
" .
            "Q: Open inventory.
" .
            "A: I will ask for confirmation before opening Inventory Management.
" .
            "Q: Buksan receivables.
" .
            "A: I will open Accounts Receivable if your account has access.";
        cb_reply_json($reply, ['topic' => 'help']);
    }

    if ($isHelpQuestion || $isPossibleQuestionsQuestion) {
        $reply = "Here are possible questions you can ask me:

" .
            "Inventory:
" .
            "• Which items are low stock?
" .
            "• Do I have out-of-stock products?
" .
            "• Show recent stock movement.
" .
            "• Paano mag-add ng product?
" .
            "• Anong produkto ang paubos?

" .
            "Sales and Analytics:
" .
            "• What are my sales today?
" .
            "• How about the sales for the whole year?
" .
            "• What are my sales this year?
" .
            "• What is my net profit today?
" .
            "• Compare this month vs last month.
" .
            "• What are my top 5 products this year?

" .
            "Receivables:
" .
            "• Who has unpaid balances?
" .
            "• Do I have overdue receivables?
" .
            "• Which customer account should I follow up first?
" .
            "• Sino may utang?

" .
            "Navigation:
" .
            "• Open inventory.
" .
            "• Open sales analytics.
" .
            "• Buksan receivables.

" .
            "I can answer using your actual database records, so the totals and item lists will depend on your saved data.";
        cb_reply_json($reply, ['topic' => 'help']);
    }

    if ($isBotIdentity) {
        cb_reply_json("I’m your NextGen Assistant. You can ask me about:\n• Low stock and out-of-stock products\n• Sales summaries and profit\n• Top-selling or slow-moving products\n• Accounts receivable and unpaid balances\n• Alerts and recommendations\n• How to use or open system modules");
    }

    /* NAVIGATION */
    if ($isOpenDashboard) cb_reply_json(cb_open_module_reply('Dashboard', '/NexGen/CODE/PHP/dashboard.php', 'I can open the dashboard for you.'));
    if ($isOpenAbout) cb_reply_json(cb_open_module_reply('About Us', '/NexGen/CODE/PHP/about_us.php', 'I can open the About Us page for you.'));
    if ($isOpenSettings) cb_reply_json(cb_open_module_reply('Settings', '/NexGen/CODE/PHP/settings.php', 'I can open Settings for you.'));

    if ($isOpenInventory) {
        cb_reply_json($GLOBALS['chatbotCanInventory']
            ? cb_open_module_reply('Inventory Management', '/NexGen/CODE/PHP/inventory_management.php', 'I can open Inventory Management for you.')
            : 'You do not currently have access to Inventory Management.');
    }

    if ($isOpenAnalytics) {
        cb_reply_json($GLOBALS['chatbotCanSalesAnalytics']
            ? cb_open_module_reply('Sales Analytics', '/NexGen/CODE/PHP/sales_analytics.php', 'I can open Sales Analytics for you.')
            : 'You do not currently have access to Sales Analytics.');
    }

    if ($isOpenSales) {
        cb_reply_json($GLOBALS['chatbotCanSales']
            ? cb_open_module_reply('Sales', '/NexGen/CODE/PHP/sales_recording.php', 'I can open the Sales module for you.')
            : 'You do not currently have access to Sales.');
    }

    if ($isOpenReceivables) {
        cb_reply_json($GLOBALS['chatbotCanAccountsReceivable']
            ? cb_open_module_reply('Accounts Receivable', '/NexGen/CODE/PHP/accounts_receivable.php', 'I can open Accounts Receivable for you.')
            : 'You do not currently have access to Accounts Receivable.');
    }

    /* HOW TO / TRAINING */
    if ($isHowAddProduct) cb_reply_json("To add a new product:\n1. Open Inventory Management.\n2. Click '+ Add Product'.\n3. Fill in product code, product name, category, unit, cost price, selling price, and stock quantity.\n4. Set reorder level and on-order level if needed.\n5. Click 'Save Product'.");
    if ($isHowRecordSale) cb_reply_json("To record a sale:\n1. Open Sales.\n2. Click 'New Sale'.\n3. Select or add the customer if needed.\n4. Choose payment status, payment method, and order status.\n5. Add product items and quantities.\n6. Review the grand total.\n7. Click 'Save Sale'.\nIf the sale is Unpaid or Partially Paid, it should be monitored in Accounts Receivable.");
    if ($isHowUpdateStock) cb_reply_json("To update stock:\n1. Open Inventory Management.\n2. Find the product in the table.\n3. Click 'Stock In/Out'.\n4. Choose Stock In or Stock Out.\n5. Enter quantity and remarks if needed.\n6. Click 'Save Movement'.");
    if ($isHowManageCategories) cb_reply_json("To manage categories:\n1. Open Inventory Management.\n2. Click 'Manage Categories'.\n3. Add or update the category name.\n4. Save the changes.");
    if ($isHowFilterAnalytics) cb_reply_json("To filter analytics:\n1. Open Sales Analytics.\n2. Choose Today, This Week, This Month, or Custom Range.\n3. The cards, charts, category results, and top products will update based on the selected period.");
    if ($isHowViewReceivables) cb_reply_json("To view receivables:\n1. Open Accounts Receivable.\n2. Review the summary cards.\n3. Use search or status filters if needed.\n4. Click 'Update Payment' when a customer makes a payment.");

    /* INVENTORY */
    if (($isLowStockQuestion || $isOutOfStockQuestion || $isNearingDepletionQuestion || $isRecentStockMovementQuestion || $isSpecificProductStockQuestion) && !$GLOBALS['chatbotCanInventory']) {
        cb_reply_json('Inventory-related questions are only available for accounts with Inventory Management access.');
    }

    if ($isLowStockQuestion) {
        $items = cb_get_low_stock($conn, 10);
        if (!empty($items)) {
            $lines = ['These are the low-stock items:'];
            foreach ($items as $item) {
                $onOrder = (int)($item['on_order_level'] ?? 0);
                $extra = $onOrder > 0 ? ", on order: {$onOrder}" : '';
                $lines[] = "• {$item['product_name']} ({$item['product_code']}) - {$item['stock_quantity']} left, reorder at {$item['reorder_level']}{$extra}";
            }
            cb_reply_json(implode("\n", $lines), ['topic' => 'inventory_low_stock']);
        }
        cb_reply_json('There are no low-stock items right now.', ['topic' => 'inventory_low_stock']);
    }

    if ($isOutOfStockQuestion) {
        $items = cb_get_out_of_stock($conn, 10);
        if (!empty($items)) {
            $lines = ['These items are out of stock:'];
            foreach ($items as $item) {
                $onOrder = (int)($item['on_order_level'] ?? 0);
                $extra = $onOrder > 0 ? " - on order: {$onOrder}" : '';
                $lines[] = "• {$item['product_name']} ({$item['product_code']}){$extra}";
            }
            cb_reply_json(implode("\n", $lines), ['topic' => 'inventory_out_stock']);
        }
        cb_reply_json('You currently have no out-of-stock products.', ['topic' => 'inventory_out_stock']);
    }

    if ($isNearingDepletionQuestion) {
        $items = cb_get_nearing_depletion($conn, 10);
        if (!empty($items)) {
            $lines = ['These items are nearing depletion:'];
            foreach ($items as $item) {
                $lines[] = "• {$item['product_name']} ({$item['product_code']}) - Stock: {$item['stock_quantity']}, Reorder: {$item['reorder_level']}";
            }
            cb_reply_json(implode("\n", $lines));
        }
        cb_reply_json('No products are currently nearing depletion beyond the low-stock list.');
    }

    if ($isRecentStockMovementQuestion) {
        $items = cb_get_recent_stock_movements($conn, 5);
        if (!empty($items)) {
            $lines = ['Here are the recent stock movements:'];
            foreach ($items as $item) {
                $label = $item['movement_type'] === 'stock_in' ? 'Stock In' : 'Stock Out';
                $lines[] = "• {$label} - {$item['product_name']} ({$item['product_code']}) | Qty: {$item['quantity']} | " . date('M d, Y h:i A', strtotime($item['created_at']));
            }
            cb_reply_json(implode("\n", $lines));
        }
        cb_reply_json('No recent stock movements were found.');
    }

    if ($isSpecificProductStockQuestion) {
        $productNameSearch = null;

        if (preg_match('/stock of (.+)$/i', $smartText, $m)) $productNameSearch = trim($m[1]);
        elseif (preg_match('/reorder level of (.+)$/i', $smartText, $m)) $productNameSearch = trim($m[1]);
        elseif (preg_match('/status of (.+)$/i', $smartText, $m)) $productNameSearch = trim($m[1]);
        elseif (preg_match('/how many left of (.+)$/i', $smartText, $m)) $productNameSearch = trim($m[1]);
        elseif (preg_match('/stock for (.+)$/i', $smartText, $m)) $productNameSearch = trim($m[1]);
        else $productNameSearch = cb_remove_question_words($smartText);

        $items = [];
        if ($productNameSearch !== null && $productNameSearch !== '') $items = cb_get_product_stock_by_name($conn, $productNameSearch);
        if (empty($items)) {
            $best = cb_find_best_product_match($conn, $smartText);
            if ($best) $items[] = $best;
        }

        if (!empty($items)) {
            $lines = ['Here’s what I found:'];
            foreach ($items as $item) {
                $onOrder = (int)($item['on_order_level'] ?? 0);
                $lines[] = "• {$item['product_name']} ({$item['product_code']}) - Stock: {$item['stock_quantity']}, Reorder: {$item['reorder_level']}, On Order: {$onOrder}";
            }
            cb_reply_json(implode("\n", $lines));
        }
        cb_reply_json('I could not find a matching product. Try asking like: Stock of Coca Cola.');
    }

    /* ALERTS */
    if ($isAlertQuestion) {
        $alerts = [];

        if ($GLOBALS['chatbotCanInventory']) {
            $lowStock = cb_get_low_stock($conn, 5);
            if (!empty($lowStock)) $alerts[] = 'Low-stock alert: ' . count($lowStock) . ' item(s) need attention.';

            $outStock = cb_get_out_of_stock($conn, 5);
            if (!empty($outStock)) $alerts[] = 'Out-of-stock alert: ' . count($outStock) . ' item(s) are out of stock.';

            $unusual = cb_get_unusual_stock_movements($conn, 5);
            if (!empty($unusual)) $alerts[] = 'Unusual movement alert: ' . count($unusual) . ' stock movement(s) had unusually large quantities.';

            $recentProducts = cb_get_recent_products($conn, 3);
            if (!empty($recentProducts)) $alerts[] = 'New product summary: ' . count($recentProducts) . ' recently added product(s).';
        }

        if ($GLOBALS['chatbotCanSalesAnalytics']) {
            $salesDrop = cb_get_sales_drop_alert($conn);
            if (!empty($salesDrop)) $alerts[] = $salesDrop;
        }

        if ($GLOBALS['chatbotCanAccountsReceivable']) {
            $overdue = cb_get_overdue_receivables($conn, 5);
            if (!empty($overdue)) $alerts[] = 'Overdue receivable alert: ' . count($overdue) . ' account(s) need follow-up.';
        }

        cb_reply_json(!empty($alerts) ? "Here are your current alerts:\n• " . implode("\n• ", $alerts) : 'You have no urgent alerts right now.');
    }

    /* SALES ANALYTICS ACCESS GUARD */
    $analyticsQuestion = $isSalesSummaryQuestion || $isGrossRevenueQuestion || $isNetProfitValueQuestion || $isCompareTodayVsYesterday || $isCompareWeekVsLastWeek || $isCompareMonthVsLastMonth || $isTopProductsQuestion || $isRevenueContributionQuestion || $isSlowMovingQuestion || $isNoRecentSalesQuestion || $isTopCategoryQuestion || $isWeakestCategoryQuestion || $isCompareCategoriesQuestion || $isCompareCategoryRevenueQuestion || $isPromoteRecommendationQuestion || $isRestockRecommendationQuestion || $isCategoryAttentionQuestion;

    if ($analyticsQuestion && !$GLOBALS['chatbotCanSalesAnalytics']) {
        cb_reply_json('Detailed sales analytics questions are mainly available for accounts with Sales Analytics access.');
    }

    /* SALES SUMMARY */
    if ($isSalesSummaryQuestion || $isNetProfitValueQuestion) {
        if (!$range) $range = cb_date_range_today();
        [$start, $end, $label] = $range;
        $summary = cb_get_summary($conn, $start, $end);

        $reply =
            "{$label} sales summary:\n" .
            '• Gross Revenue: ' . cb_money($summary['gross_revenue']) . "\n" .
            '• COGS: ' . cb_money($summary['cogs']) . "\n" .
            '• Net Profit: ' . cb_money($summary['net_profit']) . "\n" .
            '• Total Transactions: ' . number_format($summary['transactions']);

        cb_reply_json($reply, ['topic' => 'sales_summary', 'range_label' => $label]);
    }

    if ($isGrossRevenueQuestion) {
        if (!$range) $range = cb_date_range_today();
        [$start, $end, $label] = $range;
        $summary = cb_get_summary($conn, $start, $end);
        cb_reply_json("Your gross revenue for {$label} is " . cb_money($summary['gross_revenue']) . '.');
    }

    /* COMPARISONS */
    if ($isCompareTodayVsYesterday) {
        [$curStart, $curEnd] = cb_date_range_today();
        [$prevStart, $prevEnd] = cb_date_range_yesterday();
        $current = cb_get_summary($conn, $curStart, $curEnd);
        $previous = cb_get_summary($conn, $prevStart, $prevEnd);
        $growth = cb_growth_from_summaries($current['gross_revenue'], $previous['gross_revenue']);
        $label = cb_compare_label($current['gross_revenue'], $previous['gross_revenue']);
        cb_reply_json("Today vs Yesterday:\n• Today Gross Revenue: " . cb_money($current['gross_revenue']) . "\n• Yesterday Gross Revenue: " . cb_money($previous['gross_revenue']) . "\n• Sales {$label}\n• Growth: " . cb_percent($growth), ['topic' => 'sales_comparison', 'range_label' => 'Today vs Yesterday']);
    }

    if ($isCompareWeekVsLastWeek) {
        [$curStart, $curEnd] = cb_date_range_week();
        [$prevStart, $prevEnd] = cb_date_range_last_week();
        $current = cb_get_summary($conn, $curStart, $curEnd);
        $previous = cb_get_summary($conn, $prevStart, $prevEnd);
        $growth = cb_growth_from_summaries($current['gross_revenue'], $previous['gross_revenue']);
        $label = cb_compare_label($current['gross_revenue'], $previous['gross_revenue']);
        $explain = $label === 'decreased' ? 'Sales dropped this week compared to last week.' : ($label === 'increased' ? 'Sales improved this week compared to last week.' : 'Sales stayed at the same level this week.');
        cb_reply_json("This Week vs Last Week:\n• This Week Gross Revenue: " . cb_money($current['gross_revenue']) . "\n• Last Week Gross Revenue: " . cb_money($previous['gross_revenue']) . "\n• Result: {$explain}\n• Growth: " . cb_percent($growth), ['topic' => 'sales_comparison', 'range_label' => 'This Week vs Last Week']);
    }

    if ($isCompareMonthVsLastMonth) {
        [$curStart, $curEnd] = cb_date_range_month();
        [$prevStart, $prevEnd] = cb_date_range_last_month();
        $current = cb_get_summary($conn, $curStart, $curEnd);
        $previous = cb_get_summary($conn, $prevStart, $prevEnd);
        $growth = cb_growth_from_summaries($current['gross_revenue'], $previous['gross_revenue']);
        $label = cb_compare_label($current['gross_revenue'], $previous['gross_revenue']);
        cb_reply_json("This Month vs Last Month:\n• This Month Gross Revenue: " . cb_money($current['gross_revenue']) . "\n• Last Month Gross Revenue: " . cb_money($previous['gross_revenue']) . "\n• Sales {$label}\n• Growth: " . cb_percent($growth), ['topic' => 'sales_comparison', 'range_label' => 'This Month vs Last Month']);
    }

    /* PRODUCT PERFORMANCE */
    if ($isTopProductsQuestion) {
        if (!$range) $range = cb_date_range_month();
        [$start, $end, $label] = $range;
        $items = cb_get_top_products_by_qty($conn, $start, $end, 5);
        if (!empty($items)) {
            $lines = ["Top 5 products for {$label} by quantity sold:"];
            $rank = 1;
            foreach ($items as $item) $lines[] = $rank++ . ". {$item['product_name']} - {$item['qty_sold']} unit(s)";
            cb_reply_json(implode("\n", $lines), ['topic' => 'top_products', 'range_label' => $label]);
        }
        cb_reply_json("No top-selling products were found for {$label}.", ['topic' => 'top_products', 'range_label' => $label]);
    }

    if ($isRevenueContributionQuestion) {
        if (!$range) $range = cb_date_range_month();
        [$start, $end, $label] = $range;
        $items = cb_get_top_products_by_revenue($conn, $start, $end, 5);
        if (!empty($items)) {
            $lines = ["Top products for {$label} by revenue contribution:"];
            $rank = 1;
            foreach ($items as $item) $lines[] = $rank++ . ". {$item['product_name']} - " . cb_money($item['revenue']);
            cb_reply_json(implode("\n", $lines), ['topic' => 'revenue_products', 'range_label' => $label]);
        }
        cb_reply_json("No product revenue data was found for {$label}.", ['topic' => 'revenue_products', 'range_label' => $label]);
    }

    if ($isSlowMovingQuestion) {
        if (!$range) $range = cb_date_range_month();
        [$start, $end, $label] = $range;
        $items = cb_get_slow_products($conn, $start, $end, 5);
        if (!empty($items)) {
            $lines = ["Slow-moving products for {$label}:"];
            foreach ($items as $item) $lines[] = "• {$item['product_name']} - {$item['qty_sold']} unit(s)";
            cb_reply_json(implode("\n", $lines));
        }
        cb_reply_json('No slow-moving product data was found.');
    }

    if ($isNoRecentSalesQuestion) {
        $items = cb_get_no_recent_sales($conn, 30, 10);
        if (!empty($items)) {
            $lines = ['Products with no sales in the last 30 days:'];
            foreach ($items as $item) $lines[] = "• {$item['product_name']}";
            cb_reply_json(implode("\n", $lines));
        }
        cb_reply_json('All listed products had sales activity within the last 30 days.');
    }

    /* CATEGORY PERFORMANCE */
    if ($isTopCategoryQuestion) {
        if (!$range) $range = cb_date_range_month();
        [$start, $end, $label] = $range;
        $items = cb_get_category_units($conn, $start, $end);
        cb_reply_json(!empty($items) ? "The top-selling category for {$label} is {$items[0]['category_name']} with {$items[0]['units_sold']} unit(s) sold." : "No category sales data was found for {$label}.");
    }

    if ($isWeakestCategoryQuestion) {
        if (!$range) $range = cb_date_range_month();
        [$start, $end, $label] = $range;
        $items = cb_get_category_units($conn, $start, $end);
        if (!empty($items)) {
            $lowest = $items[count($items) - 1];
            cb_reply_json("The weakest category for {$label} is {$lowest['category_name']} with {$lowest['units_sold']} unit(s) sold.");
        }
        cb_reply_json("No category data was found for {$label}.");
    }

    if ($isCompareCategoriesQuestion) {
        if (!$range) $range = cb_date_range_month();
        [$start, $end, $label] = $range;
        $items = cb_get_category_units($conn, $start, $end);
        if (!empty($items)) {
            $lines = ["Category comparison by units sold for {$label}:"];
            foreach ($items as $item) $lines[] = "• {$item['category_name']} - {$item['units_sold']} unit(s)";
            cb_reply_json(implode("\n", $lines));
        }
        cb_reply_json('No category comparison data was found.');
    }

    if ($isCompareCategoryRevenueQuestion) {
        if (!$range) $range = cb_date_range_month();
        [$start, $end, $label] = $range;
        $items = cb_get_category_revenue($conn, $start, $end);
        if (!empty($items)) {
            $lines = ["Category comparison by revenue for {$label}:"];
            foreach ($items as $item) $lines[] = "• {$item['category_name']} - " . cb_money($item['revenue']);
            cb_reply_json(implode("\n", $lines));
        }
        cb_reply_json('No category revenue data was found.');
    }

    /* RECOMMENDATIONS */
    if ($isPromoteRecommendationQuestion) {
        [$start, $end] = cb_date_range_month();
        $slow = cb_get_slow_products($conn, $start, $end, 3);
        if (!empty($slow)) {
            $lines = ['Products that may need promotion:'];
            foreach ($slow as $item) $lines[] = "• {$item['product_name']} - only {$item['qty_sold']} unit(s) sold";
            cb_reply_json(implode("\n", $lines) . "\nThese items may benefit from discounts, bundling, or better product visibility.");
        }
        cb_reply_json('I could not determine promotion priorities right now.');
    }

    if ($isRestockRecommendationQuestion) {
        $low = cb_get_low_stock($conn, 5);
        $out = cb_get_out_of_stock($conn, 5);
        $lines = [];

        if (!empty($out)) {
            $lines[] = 'Restock immediately:';
            foreach ($out as $item) $lines[] = "• {$item['product_name']} - currently out of stock";
        }
        if (!empty($low)) {
            $lines[] = 'Restock soon:';
            foreach ($low as $item) $lines[] = "• {$item['product_name']} - {$item['stock_quantity']} left";
        }

        cb_reply_json(!empty($lines) ? implode("\n", $lines) : 'You currently have no urgent restocking recommendation.');
    }

    if ($isCategoryAttentionQuestion) {
        [$start, $end, $label] = cb_date_range_month();
        $items = cb_get_category_units($conn, $start, $end);
        if (!empty($items)) {
            $lowest = $items[count($items) - 1];
            cb_reply_json("The category that needs the most attention for {$label} is {$lowest['category_name']}, because it has the lowest units sold.");
        }
        cb_reply_json('I could not determine category priorities right now.');
    }

    /* RECEIVABLES */
    $receivableQuestion = $isOverdueReceivableQuestion || $isReceivableSummaryQuestion || $isUnpaidBalancesQuestion || $isFollowupPriorityQuestion;
    if ($receivableQuestion && !$GLOBALS['chatbotCanAccountsReceivable']) {
        cb_reply_json('Receivable-related questions are only available for accounts with Accounts Receivable access.');
    }

    if ($isOverdueReceivableQuestion) {
        $items = cb_get_overdue_receivables($conn, 10);
        if (!empty($items)) {
            $lines = ['These receivables are overdue:'];
            foreach ($items as $item) {
                $due = !empty($item['due_date']) ? $item['due_date'] : 'No due date';
                $lines[] = "• {$item['customer_name']} | {$item['sales_no']} | Balance: " . cb_money($item['balance_due']) . " | Due: {$due}";
            }
            cb_reply_json(implode("\n", $lines));
        }
        cb_reply_json('You currently have no overdue receivables.');
    }

    if ($isUnpaidBalancesQuestion) {
        $items = cb_get_unpaid_receivables($conn, 10);
        if (!empty($items)) {
            $lines = ['Customers with unpaid or remaining balances:'];
            foreach ($items as $item) {
                $due = !empty($item['due_date']) ? $item['due_date'] : 'No due date';
                $lines[] = "• {$item['customer_name']} | {$item['sales_no']} | Balance: " . cb_money($item['balance_due']) . " | Due: {$due}";
            }
            cb_reply_json(implode("\n", $lines));
        }
        cb_reply_json('There are no unpaid balances right now.');
    }

    if ($isReceivableSummaryQuestion) {
        $summary = cb_get_receivable_summary($conn);
        if ($summary !== null) {
            cb_reply_json(
                "Accounts receivable summary:\n" .
                '• Total Receivables: ' . (int)$summary['total_receivables'] . "\n" .
                '• Total Balance Due: ' . cb_money($summary['total_balance_due']) . "\n" .
                '• Overdue: ' . (int)$summary['overdue_count'] . "\n" .
                '• Unpaid: ' . (int)$summary['unpaid_count'] . "\n" .
                '• Partially Paid: ' . (int)$summary['partial_count']
            );
        }
        cb_reply_json('The accounts receivable table is not available yet.');
    }

    if ($isFollowupPriorityQuestion) {
        $items = cb_get_followup_priority($conn, 5);
        if (!empty($items)) {
            $lines = ['These accounts should be followed up first:'];
            foreach ($items as $item) {
                $due = !empty($item['due_date']) ? $item['due_date'] : 'No due date';
                $status = $item['live_status'] ?? 'Unpaid';
                $lines[] = "• {$item['customer_name']} | {$item['sales_no']} | Balance: " . cb_money($item['balance_due']) . " | Status: {$status} | Due: {$due}";
            }
            cb_reply_json(implode("\n", $lines));
        }
        cb_reply_json('There are no receivable follow-up priorities at the moment.');
    }

    /* MEANINGS / DEFINITIONS */
    if ($isCogsQuestion) cb_reply_json('COGS means Cost of Goods Sold. It is the total product cost of the items that were sold. It helps measure how much the sold inventory cost your business.');
    if ($isNetProfitQuestion) cb_reply_json('Net profit is the amount left after subtracting Cost of Goods Sold from gross revenue in your current analytics computation.');
    if ($isReorderLevelQuestion) cb_reply_json('Reorder level is the minimum stock quantity that tells you when to restock a product. When stock reaches or falls below that level, the item should be replenished.');
    if ($isSalesGrowthQuestion) cb_reply_json('Sales growth measures whether your sales increased or decreased compared to a previous period, such as last week or last month. It is shown as a percentage.');
    if ($isTopSellingMeaningQuestion) cb_reply_json('Top-selling products are the items with the highest sales performance, either by quantity sold or by revenue contribution, depending on the report.');
    if ($isStockMovementMeaningQuestion) cb_reply_json('Stock movement records inventory changes such as Stock In and Stock Out. It helps track why product quantity increased or decreased.');
    if ($isReceivableMeaningQuestion) cb_reply_json('Accounts receivable refers to the money customers still owe your business for unpaid or partially paid sales.');
    if ($isGrossRevenueMeaningQuestion) cb_reply_json('Gross revenue is the total amount of money earned from sales before subtracting any costs such as product cost or expenses.');

    /* SMART FALLBACKS */
    if (cb_fuzzy_has_any_word($smartText, ['sales', 'sale', 'profit', 'revenue'])) {
        cb_reply_json("I think your question is related to sales. You can ask:\n• What are my sales today?\n• What are my sales this week?\n• What is my net profit today?\n• Compare this week vs last week\n• What are my top 5 products?");
    }

    if (cb_fuzzy_has_any_word($smartText, ['inventory', 'stock', 'product', 'items'])) {
        cb_reply_json("I think your question is related to inventory. You can ask:\n• Which items are low stock?\n• Do I have out-of-stock products?\n• Show recent stock movement\n• Stock of Coca Cola\n\nYou can also ask in Taglish, like: 'Anong products ang low stock?'");
    }

    if (cb_fuzzy_has_any_word($smartText, ['receivable', 'overdue', 'balance', 'unpaid'])) {
        cb_reply_json("I think your question is related to receivables. You can ask:\n• Who has unpaid balances?\n• Do I have overdue receivables?\n• Receivable summary\n• Which customer account should I follow up first?");
    }

    cb_reply_json("I’m sorry, I couldn’t understand that yet. Try asking about sales, inventory, receivables, alerts, recommendations, or system help.");
}
?>

<div class="nx-chatbot-widget" id="nxChatbotWidget">
    <button type="button" class="nx-chatbot-toggle" id="nxChatbotToggle">
        <span class="nx-chatbot-toggle-text">Ask NextGen AI</span>
        <span class="nx-chatbot-toggle-icon-wrap">
            <img src="/NexGen/IMAGES/chatbot.png" alt="Chatbot" class="nx-chatbot-toggle-logo">
        </span>
    </button>

    <div class="nx-chatbot-box" id="nxChatbotBox">
        <div class="nx-chatbot-header">
            <div class="nx-chatbot-title">
                <img src="/NexGen/IMAGES/chatbot.png" alt="Bot">
                <div>
                    <h4>NextGen Assistant</h4>
                    <small><?php echo $chatbotIsOwner ? 'Owner AI Helper' : 'System Helper'; ?></small>
                </div>
            </div>
            <button type="button" class="nx-chatbot-close" id="nxChatbotClose">&times;</button>
        </div>

        <div class="nx-chatbot-body" id="nxChatbotMessages">
            <div class="nx-msg bot">
                Hi! I’m your NextGen Assistant. You can ask me about inventory, sales, receivables, alerts, recommendations, or system help.
                <br><br>
                Example: <strong>Which items are low stock?</strong>
            </div>
        </div>

        <div class="nx-chatbot-suggestions">
            <?php if ($chatbotIsOwner): ?>
                <button type="button" class="nx-chip">What are my sales today?</button>
                <button type="button" class="nx-chip">What are my sales this year?</button>
                <button type="button" class="nx-chip">Which items are low stock?</button>
                <button type="button" class="nx-chip">What alerts do I have today?</button>
                <button type="button" class="nx-chip">Which customer account should I follow up first?</button>
            <?php else: ?>
                <button type="button" class="nx-chip">Which items are low stock?</button>
                <button type="button" class="nx-chip">Do I have out-of-stock products?</button>
                <button type="button" class="nx-chip">How do I record a sale?</button>
                <button type="button" class="nx-chip">Show recent stock movement</button>
            <?php endif; ?>
        </div>

        <form class="nx-chatbot-input-wrap" id="nxChatbotForm">
            <input type="text" id="nxChatbotInput" placeholder="Ask something..." autocomplete="off">
            <button type="submit">Send</button>
        </form>
    </div>
</div>

<style>
.nx-chatbot-widget{
    position:fixed;
    right:18px;
    bottom:18px;
    z-index:99998;
    font-family:Arial, sans-serif;
}

.nx-chatbot-toggle{
    min-width:178px;
    height:60px;
    padding:7px 10px 7px 14px;
    border:none;
    border-radius:999px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    cursor:pointer;
    color:#fff;
    background:rgba(20,40,90,.46);
    backdrop-filter:blur(14px) saturate(145%);
    -webkit-backdrop-filter:blur(14px) saturate(145%);
    border:1px solid rgba(255,255,255,.16);
    box-shadow:0 14px 30px rgba(0,0,0,.24), 0 0 20px rgba(77,125,255,.16);
    transition:.22s ease;
}

.nx-chatbot-toggle:hover{
    transform:translateY(-2px);
    box-shadow:0 18px 34px rgba(0,0,0,.28), 0 0 26px rgba(77,125,255,.22);
}

.nx-chatbot-toggle-text{
    font-size:12px;
    font-weight:800;
    letter-spacing:.3px;
    white-space:nowrap;
}

.nx-chatbot-toggle-icon-wrap{
    width:44px;
    height:44px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
}

.nx-chatbot-toggle-icon-wrap::after{
    content:"";
    position:absolute;
    inset:-5px;
    border-radius:50%;
    border:2px solid rgba(120,190,255,.42);
    animation:nxPulse 1.8s infinite;
}

.nx-chatbot-toggle-logo{
    width:44px;
    height:44px;
    border-radius:50%;
    object-fit:cover;
}

@keyframes nxPulse{
    0%{transform:scale(.88);opacity:.8;}
    70%{transform:scale(1.18);opacity:0;}
    100%{transform:scale(.88);opacity:0;}
}

.nx-chatbot-box{
    display:none;
    position:absolute;
    right:0;
    bottom:74px;
    width:390px;
    max-width:calc(100vw - 24px);
    height:560px;
    max-height:calc(100vh - 110px);
    overflow:hidden;
    border-radius:24px;
    background:linear-gradient(180deg, rgba(12,23,55,.92), rgba(6,13,33,.96));
    border:1px solid rgba(255,255,255,.14);
    box-shadow:0 22px 60px rgba(0,0,0,.42), 0 0 30px rgba(77,125,255,.14);
    backdrop-filter:blur(18px) saturate(150%);
    -webkit-backdrop-filter:blur(18px) saturate(150%);
}

.nx-chatbot-box.show{
    display:flex;
    flex-direction:column;
    animation:nxGlowIn .25s ease;
}

@keyframes nxGlowIn{
    from{opacity:0;transform:translateY(10px) scale(.985);}
    to{opacity:1;transform:translateY(0) scale(1);}
}

.nx-chatbot-header{
    padding:14px 16px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    background:linear-gradient(135deg, rgba(55,102,235,.42), rgba(12,23,55,.28));
    border-bottom:1px solid rgba(255,255,255,.10);
}

.nx-chatbot-title{
    display:flex;
    align-items:center;
    gap:12px;
    color:#fff;
}

.nx-chatbot-title img{
    width:48px;
    height:48px;
    border-radius:50%;
    object-fit:cover;
    box-shadow:0 0 18px rgba(95,141,255,.28);
}

.nx-chatbot-title h4{
    margin:0;
    font-size:16px;
    line-height:1.1;
}

.nx-chatbot-title small{
    display:block;
    margin-top:4px;
    color:rgba(255,255,255,.68);
    font-size:12px;
}

.nx-chatbot-close{
    width:34px;
    height:34px;
    border:none;
    border-radius:50%;
    background:rgba(255,255,255,.10);
    color:#fff;
    font-size:24px;
    line-height:1;
    cursor:pointer;
    transition:.2s ease;
}

.nx-chatbot-close:hover{
    background:rgba(255,255,255,.18);
    transform:rotate(90deg);
}

.nx-chatbot-body{
    flex:1;
    overflow-y:auto;
    padding:16px;
    color:#fff;
    background:
        radial-gradient(circle at 20% 10%, rgba(77,125,255,.14), transparent 35%),
        radial-gradient(circle at 90% 80%, rgba(56,189,248,.10), transparent 35%);
}

.nx-msg{
    max-width:88%;
    padding:12px 14px;
    border-radius:18px;
    margin-bottom:12px;
    font-size:14px;
    line-height:1.45;
    white-space:normal;
    word-wrap:break-word;
    box-shadow:0 10px 22px rgba(0,0,0,.14);
}

.nx-msg.bot{
    background:rgba(255,255,255,.09);
    border:1px solid rgba(255,255,255,.08);
    color:#fff;
    border-top-left-radius:8px;
}

.nx-msg.user{
    margin-left:auto;
    background:linear-gradient(180deg,#5f8dff,#3766eb);
    color:#fff;
    border-top-right-radius:8px;
}

.nx-chatbot-suggestions{
    padding:12px 12px 8px;
    display:flex;
    gap:8px;
    overflow-x:auto;
    border-top:1px solid rgba(255,255,255,.06);
    background:rgba(255,255,255,.03);
}

.nx-chatbot-suggestions::-webkit-scrollbar{display:none;}

.nx-chip{
    flex:0 0 auto;
    border:1px solid rgba(255,255,255,.10);
    border-radius:999px;
    background:rgba(255,255,255,.08);
    color:#fff;
    padding:8px 12px;
    font-size:12px;
    cursor:pointer;
    white-space:nowrap;
    transition:.22s ease;
}

.nx-chip:hover{
    background:rgba(91,132,255,.18);
    transform:translateY(-1px);
}

.nx-chatbot-input-wrap{
    display:flex;
    gap:8px;
    padding:12px;
    background:rgba(255,255,255,.03);
    border-top:1px solid rgba(255,255,255,.08);
}

.nx-chatbot-input-wrap input{
    flex:1;
    border:none;
    outline:none;
    border-radius:14px;
    padding:12px 14px;
    background:rgba(255,255,255,.09);
    color:#fff;
    font-size:14px;
}

.nx-chatbot-input-wrap input::placeholder{color:rgba(255,255,255,.55);}

.nx-chatbot-input-wrap button,
.nx-open-btn,
.nx-cancel-btn{
    border:none;
    border-radius:14px;
    padding:10px 15px;
    color:#fff;
    font-weight:700;
    cursor:pointer;
}

.nx-chatbot-input-wrap button,
.nx-open-btn{
    background:linear-gradient(180deg,#5f8dff,#3766eb);
    box-shadow:0 10px 22px rgba(55,102,235,.22);
}

.nx-cancel-btn{background:rgba(255,255,255,.12);}

.nx-open-card{
    margin-top:10px;
    padding:12px;
    border-radius:16px;
    background:rgba(255,255,255,.07);
    border:1px solid rgba(255,255,255,.08);
}

.nx-open-card strong{display:block;margin-bottom:8px;}
.nx-open-actions{display:flex;gap:8px;flex-wrap:wrap;}

.nx-typing{
    display:inline-flex;
    align-items:center;
    gap:6px;
    background:rgba(255,255,255,.08);
    padding:12px 14px;
    border-radius:16px;
    border-top-left-radius:8px;
    margin-bottom:12px;
}

.nx-typing span{
    width:8px;
    height:8px;
    border-radius:50%;
    background:rgba(255,255,255,.75);
    animation:nxTyping 1.2s infinite ease-in-out;
}

.nx-typing span:nth-child(2){animation-delay:.15s;}
.nx-typing span:nth-child(3){animation-delay:.3s;}

@keyframes nxTyping{
    0%,80%,100%{transform:scale(.7);opacity:.5;}
    40%{transform:scale(1);opacity:1;}
}

.nx-chatbot-body::-webkit-scrollbar{width:8px;}
.nx-chatbot-body::-webkit-scrollbar-track{background:rgba(255,255,255,.03);border-radius:999px;}
.nx-chatbot-body::-webkit-scrollbar-thumb{background:linear-gradient(180deg, rgba(95,141,255,.75), rgba(55,102,235,.75));border-radius:999px;}

@media (max-width:600px){
    .nx-chatbot-widget{right:12px;bottom:12px;}
    .nx-chatbot-toggle{min-width:150px;height:52px;padding:6px 8px 6px 10px;}
    .nx-chatbot-toggle-text{font-size:11px;}
    .nx-chatbot-toggle-icon-wrap,.nx-chatbot-toggle-logo{width:40px;height:40px;}
    .nx-chatbot-box{width:min(92vw,390px);height:72vh;bottom:62px;}
}
</style>

<script>
(function(){
    const toggleBtn = document.getElementById("nxChatbotToggle");
    const chatBox = document.getElementById("nxChatbotBox");
    const closeBtn = document.getElementById("nxChatbotClose");
    const form = document.getElementById("nxChatbotForm");
    const input = document.getElementById("nxChatbotInput");
    const messages = document.getElementById("nxChatbotMessages");
    const chips = document.querySelectorAll(".nx-chip");
    const ENDPOINT = "/NexGen/CODE/PHP/chatbot.php?action=ask";

    if (!toggleBtn || !chatBox || !closeBtn || !form || !input || !messages) return;

    function escapeHtml(text){
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    function appendMessage(type, html){
        const msg = document.createElement("div");
        msg.className = "nx-msg " + type;
        msg.innerHTML = html;
        messages.appendChild(msg);
        messages.scrollTop = messages.scrollHeight;
    }

    function appendTyping(){
        const typing = document.createElement("div");
        typing.className = "nx-typing";
        typing.id = "nxTyping";
        typing.innerHTML = "<span></span><span></span><span></span>";
        messages.appendChild(typing);
        messages.scrollTop = messages.scrollHeight;
    }

    function removeTyping(){
        const typing = document.getElementById("nxTyping");
        if (typing) typing.remove();
    }

    function renderBotReply(reply){
        if (!reply) return escapeHtml("No response received.");
        const trimmed = String(reply).trim();

        if (trimmed.includes("[OPEN_CONFIRM]|")) {
            const lines = trimmed.split("\n");
            const markerLine = lines.find(line => line.includes("[OPEN_CONFIRM]|"));
            const normalLines = lines.filter(line => !line.includes("[OPEN_CONFIRM]|"));
            const parts = markerLine.split("|");
            const label = parts[1] || "Module";
            const url = parts[2] || "#";

            let html = "";
            if (normalLines.length > 0) {
                html += escapeHtml(normalLines.join("\n")).replace(/\n/g, "<br>") + "<br>";
            }

            html += `
                <div class="nx-open-card">
                    <strong>${escapeHtml(label)}</strong>
                    <div class="nx-open-actions">
                        <button type="button" class="nx-open-btn" data-open-url="${escapeHtml(url)}">Open Now</button>
                        <button type="button" class="nx-cancel-btn">Cancel</button>
                    </div>
                </div>
            `;
            return html;
        }

        return escapeHtml(trimmed).replace(/\n/g, "<br>");
    }

    async function sendMessage(text){
        const message = (text || input.value).trim();
        if (!message) return;

        appendMessage("user", escapeHtml(message).replace(/\n/g, "<br>"));
        input.value = "";
        appendTyping();

        try {
            const formData = new FormData();
            formData.append("message", message);

            const response = await fetch(ENDPOINT, {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            });

            const rawText = await response.text();
            removeTyping();

            let data = null;
            try {
                data = JSON.parse(rawText);
            } catch (parseError) {
                console.error("Chatbot non-JSON response:", rawText);
                appendMessage("bot", "Sorry, I had trouble reading the server reply. Please refresh the page and try again.");
                return;
            }

            if (!response.ok || !data.success) {
                appendMessage("bot", renderBotReply(data.reply || "Sorry, something went wrong while getting a reply."));
                return;
            }

            if (data && data.reply) {
                appendMessage("bot", renderBotReply(data.reply));
            } else {
                appendMessage("bot", "Sorry, something went wrong while getting a reply.");
            }
        } catch (error) {
            removeTyping();
            console.error("Chatbot fetch error:", error);
            appendMessage("bot", "Sorry, I couldn’t connect right now. Please refresh the page and try again.");
        }
    }

    toggleBtn.addEventListener("click", function(){
        chatBox.classList.toggle("show");
        if (chatBox.classList.contains("show")) setTimeout(() => input.focus(), 120);
    });

    closeBtn.addEventListener("click", function(){
        chatBox.classList.remove("show");
    });

    form.addEventListener("submit", function(e){
        e.preventDefault();
        sendMessage();
    });

    chips.forEach(chip => {
        chip.addEventListener("click", function(){
            const text = this.textContent.trim();
            if (!chatBox.classList.contains("show")) chatBox.classList.add("show");
            sendMessage(text);
        });
    });

    messages.addEventListener("click", function(e){
        const openBtn = e.target.closest(".nx-open-btn");
        const cancelBtn = e.target.closest(".nx-cancel-btn");

        if (openBtn) {
            const url = openBtn.getAttribute("data-open-url");
            if (url) window.location.href = url;
        }

        if (cancelBtn) {
            const card = cancelBtn.closest(".nx-open-card");
            if (card) card.remove();
        }
    });
})();
</script>
