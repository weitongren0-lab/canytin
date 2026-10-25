<?php
require_once __DIR__ . '/auth.php';
$conn = getDB();

$conn->query("CREATE TABLE IF NOT EXISTS menu (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, price DECIMAL(10,2) NOT NULL, category VARCHAR(50) DEFAULT '热菜', image VARCHAR(255) DEFAULT '', available TINYINT(1) DEFAULT 1)");
$conn->query("CREATE TABLE IF NOT EXISTS orders (id INT AUTO_INCREMENT PRIMARY KEY, table_no VARCHAR(20) NOT NULL, phone VARCHAR(20) DEFAULT '', items TEXT NOT NULL, total DECIMAL(10,2) NOT NULL, note VARCHAR(255) DEFAULT '', status VARCHAR(20) DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $name = $conn->real_escape_string($_POST['name']); $price = floatval($_POST['price']); $cat = $conn->real_escape_string($_POST['category']); $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION); $filename = time() . '_' . rand(1000, 9999) . '.' . $ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) $image = 'uploads/' . $filename;
    }
    $conn->query("INSERT INTO menu (name, price, category, image) VALUES ('$name', $price, '$cat', '$image')");
    header('Location: admin.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    $id = intval($_POST['id']); $name = $conn->real_escape_string($_POST['name']); $price = floatval($_POST['price']); $cat = $conn->real_escape_string($_POST['category']);
    $conn->query("UPDATE menu SET name='$name', price=$price, category='$cat' WHERE id=$id");
    header('Location: admin.php'); exit;
}
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']); $row = $conn->query("SELECT image FROM menu WHERE id=$id")->fetch_assoc();
    if ($row && $row['image'] && file_exists(__DIR__ . '/' . $row['image'])) unlink(__DIR__ . '/' . $row['image']);
    $conn->query("DELETE FROM menu WHERE id=$id"); header('Location: admin.php'); exit;
}
if (isset($_GET['toggle'])) { $id = intval($_GET['toggle']); $conn->query("UPDATE menu SET available = 1 - available WHERE id=$id"); header('Location: admin.php'); exit; }

$dateFilter = $_GET['date'] ?? '';
$orderQuery = "SELECT * FROM orders";
if ($dateFilter) { $dateFilterEsc = $conn->real_escape_string($dateFilter); $orderQuery .= " WHERE DATE(created_at)='$dateFilterEsc'"; }
$orderQuery .= " ORDER BY id DESC LIMIT 200";
$orderList = $conn->query($orderQuery);
$menu = $conn->query("SELECT * FROM menu ORDER BY id DESC");
$editItem = isset($_GET['edit']) ? $conn->query("SELECT * FROM menu WHERE id=" . intval($_GET['edit']))->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>菜品管理</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; } body { font-family: -apple-system, sans-serif; background: #f5f5f5; padding: 15px; }
h1 { text-align: center; margin-bottom: 15px; color: #333; font-size: 22px; }
.tabs { display: flex; margin-bottom: 15px; border-bottom: 2px solid #eee; } .tabs .tab { flex: 1; text-align: center; padding: 12px; cursor: pointer; color: #666; font-size: 15px; border-bottom: 3px solid transparent; } .tabs .tab.active { color: #e74c3c; border-bottom-color: #e74c3c; font-weight: bold; }
.panel { display: none; } .panel.active { display: block; }
.card { background: #fff; padding: 18px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.form-row { display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; } .form-row input, .form-row select { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 15px; min-width: 100px; }
.form-row button { background: #e74c3c; color: #fff; border: none; padding: 10px 24px; border-radius: 5px; font-size: 15px; cursor: pointer; } .form-row button.cancel { background: #95a5a6; }
.item-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; } .item { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); } .item.off { opacity: 0.5; }
.item img { width: 100%; height: 130px; object-fit: cover; background: #eee; } .item .info { padding: 10px; } .item .name { font-weight: bold; font-size: 15px; } .item .price { color: #e74c3c; font-size: 17px; margin-top: 4px; } .item .cat { color: #999; font-size: 12px; margin-top: 3px; }
.item .actions { display: flex; border-top: 1px solid #f0f0f0; } .item .actions a { flex: 1; text-align: center; padding: 8px 0; font-size: 13px; text-decoration: none; color: #666; border-right: 1px solid #f0f0f0; } .item .actions a:last-child { border-right: none; } .item .actions a.danger { color: #e74c3c; } .item .actions a.toggle { color: #f39c12; }
.empty { text-align: center; color: #999; padding: 40px; }
table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; } th, td { padding: 10px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 13px; } th { background: #fafafa; color: #666; font-weight: normal; }
.status-tag { padding: 2px 10px; border-radius: 10px; font-size: 12px; color: #fff; } .status-pending { background: #f39c12; } .status-cooking { background: #3498db; } .status-ready { background: #2ecc71; } .status-done { background: #95a5a6; }
.date-filter { display: flex; gap: 10px; margin-bottom: 15px; align-items: center; } .date-filter input { padding: 8px; border: 1px solid #ddd; border-radius: 5px; } .date-filter button { padding: 8px 20px; background: #e74c3c; color: #fff; border: none; border-radius: 5px; cursor: pointer; }
</style>
</head>
<body>
<h1>🍽️ 菜品管理</h1>
<div class="tabs"><div class="tab active" onclick="switchTab('menu')">菜品管理</div><div class="tab" onclick="switchTab('orders')">订单查询</div></div>

<div class="panel active" id="panel-menu">
    <div class="card">
        <h3 style="margin-bottom:12px;font-size:16px;"><?= $editItem ? '编辑菜品' : '添加新菜品' ?></h3>
        <form method="post" enctype="multipart/form-data">
            <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>
            <div class="form-row">
                <input type="text" name="name" placeholder="菜品名称" required value="<?= $editItem ? htmlspecialchars($editItem['name']) : '' ?>">
                <input type="number" name="price" step="0.01" placeholder="价格" required value="<?= $editItem ? $editItem['price'] : '' ?>">
                <select name="category"><?php foreach (['热菜','凉菜','主食','汤类','饮品'] as $c): ?><option value="<?= $c ?>" <?= $editItem && $editItem['category'] === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-row">
                <input type="file" name="image" accept="image/*">
                <?php if ($editItem): ?><button type="submit" name="edit_item">保存</button><button type="button" class="cancel" onclick="location.href='admin.php'">取消</button><?php else: ?><button type="submit" name="add_item">添加菜品</button><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="card">
        <h3 style="margin-bottom:12px;font-size:16px;">现有菜品（<?= $menu->num_rows ?> 个）</h3>
        <?php if ($menu->num_rows === 0): ?><div class="empty">还没有菜品</div><?php else: ?>
            <div class="item-list">
                <?php while ($row = $menu->fetch_assoc()): ?>
                    <div class="item <?= $row['available'] ? '' : 'off' ?>">
                        <img src="<?= $row['image'] ? htmlspecialchars($row['image']) : 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'130\'%3E%3Crect fill=\'%23ddd\' width=\'200\' height=\'130\'/%3E%3C/svg%3E' ?>">
                        <div class="info"><div class="name"><?= htmlspecialchars($row['name']) ?></div><div class="price">¥<?= number_format($row['price'], 2) ?></div><div class="cat"><?= htmlspecialchars($row['category']) ?></div></div>
                        <div class="actions"><a href="?edit=<?= $row['id'] ?>">编辑</a><a href="?toggle=<?= $row['id'] ?>" class="toggle"><?= $row['available'] ? '下架' : '上架' ?></a><a href="?delete=<?= $row['id'] ?>" class="danger" onclick="return confirm('确定删除？')">删除</a></div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="panel" id="panel-orders">
    <div class="card">
        <div class="date-filter">
            <form method="get" style="display:flex;gap:10px;align-items:center;">
                <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>"><button type="submit">查询</button>
                <?php if ($dateFilter): ?><a href="admin.php">全部</a><?php endif; ?>
            </form>
        </div>
        <?php if ($orderList->num_rows === 0): ?><div class="empty">没有订单</div><?php else: ?>
            <table>
                <tr><th>#</th><th>桌号</th><th>手机号</th><th>菜品</th><th>备注</th><th>金额</th><th>状态</th><th>时间</th></tr>
                <?php $statusMap = ['pending'=>'待接单','cooking'=>'制作中','ready'=>'已做好','done'=>'已完成']; while ($o = $orderList->fetch_assoc()): $items = json_decode($o['items'], true); $itemsText = implode('、', array_map(fn($i) => $i['name'].'×'.$i['qty'], $items ?: [])); ?>
                <tr><td>#<?= $o['id'] ?></td><td><?= $o['table_no'] ?></td><td><?= htmlspecialchars($o['phone']) ?></td><td><?= htmlspecialchars($itemsText) ?></td><td><?= htmlspecialchars($o['note']) ?></td><td>¥<?= $o['total'] ?></td><td><span class="status-tag status-<?= $o['status'] ?>"><?= $statusMap[$o['status']] ?? $o['status'] ?></span></td><td><?= $o['created_at'] ?></td></tr>
                <?php endwhile; ?>
            </table>
        <?php endif; ?>
    </div>
</div>
<script>
function switchTab(name) { document.querySelectorAll('.tab').forEach(el => el.classList.toggle('active', el.textContent.includes(name === 'menu' ? '菜品' : '订单'))); document.getElementById('panel-menu').classList.toggle('active', name === 'menu'); document.getElementById('panel-orders').classList.toggle('active', name === 'orders'); }
</script>
</body>
</html>