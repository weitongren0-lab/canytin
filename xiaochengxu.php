<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$conn = getDB();

$conn->query("CREATE TABLE IF NOT EXISTS menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(50) DEFAULT '热菜',
    image VARCHAR(255) DEFAULT '',
    available TINYINT(1) DEFAULT 1
)");
$conn->query("CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_no VARCHAR(20) NOT NULL,
    phone VARCHAR(20) DEFAULT '',
    items TEXT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    note VARCHAR(255) DEFAULT '',
    status VARCHAR(20) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

foreach ([
    "menu" => ["image" => "VARCHAR(255) DEFAULT '' AFTER category", "available" => "TINYINT(1) DEFAULT 1 AFTER image"],
    "orders" => ["phone" => "VARCHAR(20) DEFAULT '' AFTER table_no", "note" => "VARCHAR(255) DEFAULT '' AFTER total"]
] as $tbl => $cols) {
    foreach ($cols as $col => $def) {
        $c = $conn->query("SHOW COLUMNS FROM $tbl LIKE '$col'");
        if ($c && $c->num_rows === 0) $conn->query("ALTER TABLE $tbl ADD COLUMN $col $def");
    }
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($action === 'get_menu') {
        $res = $conn->query("SELECT * FROM menu WHERE available=1 ORDER BY category, id");
        $menu = [];
        while ($row = $res->fetch_assoc()) { $menu[] = $row; }
        echo json_encode(['success' => true, 'menu' => $menu]);
        exit;
    }

    if ($action === 'place_order') {
        $table = $conn->real_escape_string($input['table'] ?? '1');
        $phone = $conn->real_escape_string($input['phone'] ?? '');
        $note = $conn->real_escape_string($input['note'] ?? '');
        $items = $conn->real_escape_string(json_encode($input['items'] ?? []));
        $total = floatval($input['total'] ?? 0);
        $conn->query("INSERT INTO orders (table_no, phone, items, total, note) VALUES ('$table', '$phone', '$items', $total, '$note')");
        echo json_encode(['success' => true, 'order_id' => $conn->insert_id]);
        exit;
    }

    if ($action === 'get_orders') {
        $res = $conn->query("SELECT * FROM orders ORDER BY id DESC LIMIT 50");
        $orders = [];
        while ($row = $res->fetch_assoc()) {
            $row['items'] = json_decode($row['items'], true);
            $orders[] = $row;
        }
        echo json_encode(['success' => true, 'orders' => $orders]);
        exit;
    }

    if ($action === 'my_orders') {
        $phone = $conn->real_escape_string(getCurrentPhone());
        $res = $conn->query("SELECT * FROM orders WHERE phone='$phone' ORDER BY id DESC LIMIT 30");
        $orders = [];
        while ($row = $res->fetch_assoc()) {
            $row['items'] = json_decode($row['items'], true);
            $orders[] = $row;
        }
        echo json_encode(['success' => true, 'orders' => $orders]);
        exit;
    }

    if ($action === 'update_status') {
        $id = intval($input['id']);
        $status = $conn->real_escape_string($input['status']);
        $conn->query("UPDATE orders SET status='$status' WHERE id=$id");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'today_stats') {
        $res = $conn->query("SELECT COUNT(*) as cnt, IFNULL(SUM(total),0) as sum FROM orders WHERE DATE(created_at)=CURDATE()");
        echo json_encode(['success' => true, 'stats' => $res->fetch_assoc()]);
        exit;
    }

    exit;
}

$view = $_GET['view'] ?? 'customer';
$table_from_url = htmlspecialchars($_GET['table'] ?? '');
$user_phone = getCurrentPhone();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $view === 'kitchen' ? '后厨接单' : '在线点餐' ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
body { font-family: -apple-system, sans-serif; background: #f5f5f5; padding-bottom: 70px; }
.header { background: #e74c3c; color: #fff; padding: 14px; text-align: center; font-size: 18px; position: sticky; top: 0; z-index: 100; }
.user-bar { background: #fff; padding: 8px 15px; font-size: 13px; color: #666; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
.user-bar a { color: #e74c3c; text-decoration: none; }
.table-bar { background: #fff; padding: 14px; margin-bottom: 8px; text-align: center; font-size: 16px; color: #333; border-bottom: 2px solid #e74c3c; }
.table-bar .table-num { color: #e74c3c; font-weight: bold; font-size: 22px; }
.table-bar.warning { background: #fff3cd; color: #856404; }
.table-bar input { padding: 8px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; width: 100px; text-align: center; }
.category-nav { position: sticky; top: 50px; z-index: 90; background: #fff; display: flex; overflow-x: auto; padding: 8px 10px; gap: 8px; border-bottom: 1px solid #eee; }
.category-nav::-webkit-scrollbar { display: none; }
.cat-btn { flex-shrink: 0; padding: 6px 16px; border-radius: 15px; background: #f0f0f0; color: #666; font-size: 14px; cursor: pointer; border: none; }
.cat-btn.active { background: #e74c3c; color: #fff; }
.menu-list { padding: 10px; }
.menu-item { background: #fff; padding: 12px; margin-bottom: 8px; border-radius: 8px; display: flex; align-items: center; gap: 12px; }
.menu-item img { width: 70px; height: 70px; border-radius: 8px; object-fit: cover; background: #eee; flex-shrink: 0; }
.menu-item .info { flex: 1; min-width: 0; }
.menu-item .name { font-size: 16px; font-weight: bold; }
.menu-item .price { color: #e74c3c; font-size: 18px; margin-top: 5px; }
.btn-add { background: #e74c3c; color: #fff; border: none; width: 36px; height: 36px; border-radius: 50%; font-size: 20px; cursor: pointer; flex-shrink: 0; }
.cart-bar { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); z-index: 100; }
.cart-total { font-size: 18px; font-weight: bold; color: #e74c3c; cursor: pointer; }
.btn-submit { background: #e74c3c; color: #fff; border: none; padding: 12px 30px; border-radius: 25px; font-size: 16px; cursor: pointer; }
.btn-submit:disabled { background: #ccc; }
.cart-popup { position: fixed; bottom: 70px; left: 0; right: 0; background: #fff; border-radius: 12px 12px 0 0; box-shadow: 0 -4px 20px rgba(0,0,0,0.15); max-height: 60vh; overflow-y: auto; z-index: 99; display: none; }
.cart-popup.show { display: block; }
.cart-popup-header { padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
.cart-popup-header h3 { font-size: 16px; }
.cart-popup-header .clear-btn { color: #999; font-size: 13px; cursor: pointer; }
.cart-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid #f5f5f5; }
.cart-item .ci-name { flex: 1; font-size: 15px; }
.cart-item .ci-price { color: #e74c3c; font-weight: bold; margin: 0 12px; }
.qty-ctrl { display: flex; align-items: center; gap: 8px; }
.qty-ctrl button { width: 28px; height: 28px; border-radius: 50%; border: 1px solid #e74c3c; background: #fff; color: #e74c3c; font-size: 16px; cursor: pointer; }
.qty-ctrl .qty { min-width: 24px; text-align: center; font-size: 15px; }
.my-order-card { background: #fff; border-radius: 8px; padding: 12px; margin-bottom: 8px; border-left: 4px solid #f39c12; }
.my-order-card.cooking { border-left-color: #3498db; }
.my-order-card.ready { border-left-color: #2ecc71; }
.my-order-card.done { border-left-color: #95a5a6; opacity: 0.7; }
.my-order-card .mo-header { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 14px; }
.my-order-card .status-tag { padding: 2px 10px; border-radius: 10px; font-size: 12px; color: #fff; }
.status-pending { background: #f39c12; }
.status-cooking { background: #3498db; }
.status-ready { background: #2ecc71; }
.status-done { background: #95a5a6; }
.my-order-card .mo-items { color: #666; font-size: 13px; line-height: 1.6; }
.note-input { padding: 10px; }
.note-input textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; resize: none; height: 60px; font-family: inherit; }
.kitchen-tabs { display: flex; background: #fff; position: sticky; top: 50px; z-index: 90; border-bottom: 1px solid #eee; }
.kitchen-tabs .ktab { flex: 1; text-align: center; padding: 12px; font-size: 15px; cursor: pointer; color: #666; border-bottom: 3px solid transparent; }
.kitchen-tabs .ktab.active { color: #e74c3c; border-bottom-color: #e74c3c; font-weight: bold; }
.kitchen-tabs .ktab .count { background: #e74c3c; color: #fff; border-radius: 10px; padding: 1px 7px; font-size: 12px; margin-left: 4px; }
.order-card { background: #fff; margin: 10px; padding: 15px; border-radius: 8px; border-left: 4px solid #f39c12; }
.order-card.cooking { border-left-color: #3498db; }
.order-card.ready { border-left-color: #2ecc71; }
.order-card .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.order-card .table-badge { background: #e74c3c; color: #fff; padding: 3px 12px; border-radius: 15px; font-size: 15px; font-weight: bold; }
.order-card .time-ago { color: #999; font-size: 12px; }
.order-item { color: #333; font-size: 14px; padding: 3px 0; }
.order-note { color: #e67e22; font-size: 13px; padding: 6px 0; font-style: italic; }
.order-actions { margin-top: 10px; display: flex; gap: 8px; }
.btn-status { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
.btn-cooking { background: #3498db; color: #fff; }
.btn-ready { background: #2ecc71; color: #fff; }
.btn-done { background: #95a5a6; color: #fff; }
.empty { text-align: center; padding: 50px; color: #999; }
.stats-bar { background: #fff; padding: 12px 15px; display: flex; justify-content: space-around; border-bottom: 1px solid #eee; }
.stats-bar .stat { text-align: center; }
.stats-bar .stat .num { font-size: 20px; font-weight: bold; color: #e74c3c; }
.stats-bar .stat .lbl { font-size: 12px; color: #999; margin-top: 2px; }
.my-orders-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 200; display: none; align-items: center; justify-content: center; padding: 20px; }
.my-orders-overlay.show { display: flex; }
.mo-panel { background: #fff; border-radius: 12px; width: 100%; max-width: 420px; max-height: 80vh; display: flex; flex-direction: column; }
.mo-panel-header { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #eee; }
.mo-panel-header h3 { font-size: 17px; }
.mo-close { font-size: 20px; color: #999; cursor: pointer; padding: 0 6px; }
.mo-panel-body { overflow-y: auto; padding: 10px; }
</style>
</head>
<body>

<?php if ($view === 'kitchen'): ?>
<div class="header">🍳 后厨接单台</div>
<div class="stats-bar" id="stats-bar">
    <div class="stat"><div class="num" id="stat-cnt">-</div><div class="lbl">今日订单</div></div>
    <div class="stat"><div class="num" id="stat-sum">-</div><div class="lbl">今日金额</div></div>
</div>
<div class="kitchen-tabs">
    <div class="ktab active" data-status="pending" onclick="switchKTab('pending')">待接单 <span class="count" id="cnt-pending">0</span></div>
    <div class="ktab" data-status="cooking" onclick="switchKTab('cooking')">制作中 <span class="count" id="cnt-cooking">0</span></div>
    <div class="ktab" data-status="ready" onclick="switchKTab('ready')">已完成 <span class="count" id="cnt-ready">0</span></div>
</div>
<div id="orders-container"></div>

<script>
let allOrders = [];
let currentTab = 'pending';
let lastOrderId = 0;
function timeAgo(dateStr) {
    const t = new Date(dateStr.replace(/-/g, '/')).getTime();
    const diff = Math.floor((Date.now() - t) / 1000);
    if (diff < 60) return diff + ' 秒前';
    if (diff < 3600) return Math.floor(diff / 60) + ' 分钟前';
    if (diff < 86400) return Math.floor(diff / 3600) + ' 小时前';
    return Math.floor(diff / 86400) + ' 天前';
}
function playBeep() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.frequency.value = 800;
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
        osc.start(); osc.stop(ctx.currentTime + 0.5);
    } catch(e) {}
}
function loadOrders() {
    fetch('?action=get_orders').then(r => r.json()).then(data => {
        allOrders = data.orders || [];
        const maxId = allOrders.reduce((m, o) => Math.max(m, parseInt(o.id)), 0);
        if (lastOrderId > 0 && maxId > lastOrderId) playBeep();
        lastOrderId = maxId;
        renderOrders(); loadStats();
    });
}
function loadStats() {
    fetch('?action=today_stats').then(r => r.json()).then(data => {
        if (data.success) {
            document.getElementById('stat-cnt').textContent = data.stats.cnt;
            document.getElementById('stat-sum').textContent = '¥' + parseFloat(data.stats.sum).toFixed(0);
        }
    });
}
function switchKTab(status) {
    currentTab = status;
    document.querySelectorAll('.ktab').forEach(el => el.classList.toggle('active', el.dataset.status === status));
    renderOrders();
}
function renderOrders() {
    const counts = { pending: 0, cooking: 0, ready: 0 };
    allOrders.forEach(o => { if (counts[o.status] !== undefined) counts[o.status]++; });
    document.getElementById('cnt-pending').textContent = counts.pending;
    document.getElementById('cnt-cooking').textContent = counts.cooking;
    document.getElementById('cnt-ready').textContent = counts.ready;
    const container = document.getElementById('orders-container');
    const filtered = allOrders.filter(o => o.status === currentTab);
    if (filtered.length === 0) { container.innerHTML = '<div class="empty">暂无订单</div>'; return; }
    let html = '';
    filtered.forEach(o => {
        let itemsHtml = '';
        (o.items || []).forEach(i => { itemsHtml += `<div class="order-item">${i.name} × ${i.qty}</div>`; });
        let actionHtml = '';
        if (o.status === 'pending') actionHtml = `<button class="btn-status btn-cooking" onclick="updateStatus(${o.id}, 'cooking')">开始制作</button>`;
        else if (o.status === 'cooking') actionHtml = `<button class="btn-status btn-ready" onclick="updateStatus(${o.id}, 'ready')">制作完成</button>`;
        else if (o.status === 'ready') actionHtml = `<button class="btn-status btn-done" onclick="updateStatus(${o.id}, 'done')">已取餐</button>`;
        const noteHtml = o.note ? `<div class="order-note">📝 备注：${o.note}</div>` : '';
        html += `<div class="order-card ${o.status}">
            <div class="order-header"><span class="table-badge">${o.table_no} 号桌</span><span class="time-ago">${timeAgo(o.created_at)}</span></div>
            <div style="color:#666;font-size:13px;">👤 ${o.phone || '未留手机'}</div>
            ${itemsHtml} ${noteHtml}
            <div class="order-header" style="margin-top:8px;"><span>合计</span><span style="color:#e74c3c;font-weight:bold;">¥${o.total}</span></div>
            <div class="order-actions">${actionHtml}</div>
        </div>`;
    });
    container.innerHTML = html;
}
function updateStatus(id, status) {
    fetch('?action=update_status', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id, status}) }).then(() => loadOrders());
}
loadOrders(); setInterval(loadOrders, 5000);
</script>

<?php else: ?>
<div class="header">🍽️ 扫码点餐</div>
<div class="user-bar">
    <span>👤 <?= htmlspecialchars($user_phone) ?></span>
    <a href="javascript:void(0)" onclick="showMyOrders()">我的订单</a>
</div>

<?php if ($table_from_url): ?>
    <div class="table-bar">您的桌号：<span class="table-num"><?= $table_from_url ?></span> 号桌</div>
    <input type="hidden" id="table-no" value="<?= $table_from_url ?>">
<?php else: ?>
    <div class="table-bar warning">请输入桌号：<input type="text" id="table-no" placeholder="如：8"></div>
<?php endif; ?>

<div class="category-nav" id="category-nav"></div>
<div class="menu-list" id="menu-container"></div>
<div class="cart-popup" id="cart-popup">
    <div class="cart-popup-header"><h3>已选菜品</h3><span class="clear-btn" onclick="clearCart()">清空</span></div>
    <div id="cart-items"></div>
    <div class="note-input"><textarea id="order-note" placeholder="备注：如不要辣、少放盐..."></textarea></div>
</div>
<div class="cart-bar">
    <span class="cart-total" onclick="toggleCart()">🛒 合计：¥<span id="total">0</span></span>
    <button class="btn-submit" id="submit-btn" onclick="submitOrder()">提交订单</button>
</div>

<script>
let menuData = [], cart = {}, currentCategory = '全部';
const userPhone = '<?= htmlspecialchars($user_phone) ?>';
function loadMenu() { fetch('?action=get_menu').then(r => r.json()).then(data => { menuData = data.menu || []; renderCategories(); renderMenu(); }); }
function renderCategories() {
    const cats = ['全部']; menuData.forEach(m => { if (!cats.includes(m.category)) cats.push(m.category); });
    document.getElementById('category-nav').innerHTML = cats.map(c => `<button class="cat-btn ${c === currentCategory ? 'active' : ''}" onclick="switchCategory('${c}')">${c}</button>`).join('');
}
function switchCategory(cat) { currentCategory = cat; renderCategories(); renderMenu(); }
function renderMenu() {
    const container = document.getElementById('menu-container');
    let list = currentCategory === '全部' ? menuData : menuData.filter(m => m.category === currentCategory);
    if (list.length === 0) { container.innerHTML = '<div class="empty">暂无菜品</div>'; return; }
    container.innerHTML = list.map(item => {
        const imgSrc = item.image ? item.image : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='70' height='70'%3E%3Crect fill='%23eee' width='70' height='70'/%3E%3Ctext x='50%25' y='50%25' fill='%23bbb' text-anchor='middle' dy='.3em' font-size='12'%3E无图%3C/text%3E%3C/svg%3E";
        return `<div class="menu-item"><img src="${imgSrc}"><div class="info"><div class="name">${item.name}</div><div class="price">¥${item.price}</div></div><button class="btn-add" onclick="addToCart(${item.id}, '${item.name}', ${item.price})">+</button></div>`;
    }).join('');
}
function addToCart(id, name, price) { cart[id] ? cart[id].qty++ : cart[id] = {name, price, qty: 1}; updateCartDisplay(); }
function changeQty(id, delta) { if (!cart[id]) return; cart[id].qty += delta; if (cart[id].qty <= 0) delete cart[id]; updateCartDisplay(); }
function clearCart() { cart = {}; updateCartDisplay(); }
function updateCartDisplay() {
    let total = 0, count = 0;
    for (let id in cart) { total += cart[id].price * cart[id].qty; count += cart[id].qty; }
    document.getElementById('total').textContent = total.toFixed(2);
    document.getElementById('submit-btn').disabled = count === 0;
    const itemsEl = document.getElementById('cart-items');
    if (count === 0) { itemsEl.innerHTML = '<div style="text-align:center;padding:20px;color:#999;">购物车是空的</div>'; return; }
    itemsEl.innerHTML = Object.keys(cart).map(id => {
        const it = cart[id];
        return `<div class="cart-item"><span class="ci-name">${it.name}</span><span class="ci-price">¥${it.price}</span><div class="qty-ctrl"><button onclick="changeQty(${id}, -1)">−</button><span class="qty">${it.qty}</span><button onclick="changeQty(${id}, 1)">+</button></div></div>`;
    }).join('');
}
function toggleCart() { document.getElementById('cart-popup').classList.toggle('show'); }
function submitOrder() {
    const table = document.getElementById('table-no').value.trim();
    if (!table) return alert('请输入桌号');
    const items = []; let total = 0;
    for (let id in cart) { items.push({name: cart[id].name, qty: cart[id].qty}); total += cart[id].price * cart[id].qty; }
    if (items.length === 0) return alert('请先选择菜品');
    const note = document.getElementById('order-note').value.trim();
    fetch('?action=place_order', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({table, phone: userPhone, items, total, note}) }).then(r => r.json()).then(data => {
        if (data.success) { alert('订单已提交！'); cart = {}; document.getElementById('order-note').value = ''; updateCartDisplay(); document.getElementById('cart-popup').classList.remove('show'); }
    });
}
function showMyOrders() {
    fetch('?action=my_orders').then(r => r.json()).then(data => {
        const orders = data.orders || [];
        if (orders.length === 0) return alert('您还没有点过餐');
        const statusMap = {pending: '待接单', cooking: '制作中', ready: '已做好', done: '已完成'};
        let html = '<div class="mo-list">';
        orders.forEach(o => {
            let itemsText = (o.items || []).map(i => `${i.name}×${i.qty}`).join('、');
            html += `<div class="my-order-card ${o.status}"><div class="mo-header"><span>#${o.id} · ${o.table_no}号桌</span><span class="status-tag status-${o.status}">${statusMap[o.status] || o.status}</span></div><div class="mo-items">${itemsText}</div><div class="mo-header" style="margin-top:6px;"><span style="color:#999;">${o.created_at}</span><span style="color:#e74c3c;font-weight:bold;">¥${o.total}</span></div></div>`;
        });
        html += '</div>';
        let overlay = document.getElementById('my-orders-overlay');
        if (!overlay) {
            overlay = document.createElement('div'); overlay.id = 'my-orders-overlay'; overlay.className = 'my-orders-overlay';
            overlay.innerHTML = `<div class="mo-panel"><div class="mo-panel-header"><h3>我的订单</h3><span class="mo-close" onclick="closeMyOrders()">✕</span></div><div class="mo-panel-body" id="mo-panel-body"></div></div>`;
            document.body.appendChild(overlay);
        }
        document.getElementById('mo-panel-body').innerHTML = html;
        overlay.classList.add('show');
    });
}
function closeMyOrders() { document.getElementById('my-orders-overlay').classList.remove('show'); }
loadMenu(); updateCartDisplay();
</script>
<?php endif; ?>
</body>
</html>