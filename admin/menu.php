<?php
ob_start();
require_once __DIR__ . '/includes/database.php';
include 'hatalar.php';
include 'header.php';

$pages_result = mysqli_query($conn, "SELECT id, title, slug FROM pages ORDER BY title");
$categories_result = mysqli_query($conn, "SELECT id, name, slug FROM categories ORDER BY name");
$menus_result = mysqli_query($conn, "SELECT id, name, type FROM menus ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_menu'])) {
        $name = mysqli_real_escape_string($conn, trim($_POST['menu_name']));
        $type = mysqli_real_escape_string($conn, trim($_POST['menu_type']));
        if (!empty($name) && in_array($type, ['header', 'footer', 'custom'])) {
            mysqli_query($conn, "INSERT INTO menus (name, type) VALUES ('$name', '$type')");
            header("Location: menu.php");
            exit;
        }
    } elseif (isset($_POST['add_menu_item'])) {
        $menu_id = (int)$_POST['menu_id'];
        $title = mysqli_real_escape_string($conn, trim($_POST['title']));
        $type = mysqli_real_escape_string($conn, trim($_POST['type']));
        $order_query = mysqli_query($conn, "SELECT MAX(`order`) as max_order FROM menu_items WHERE menu_id = $menu_id");
        $order_row = mysqli_fetch_assoc($order_query);
        $order = ($order_row['max_order'] !== null) ? (int)$order_row['max_order'] + 1 : 0;
        $target_id = null;
        $slug = "";

        if ($type === 'page') {
            $target_id = (int)$_POST['page_id'];
            $page_query = mysqli_query($conn, "SELECT slug FROM pages WHERE id = $target_id");
            $slug = mysqli_real_escape_string($conn, mysqli_fetch_assoc($page_query)['slug']);
        } elseif ($type === 'category') {
            $target_id = (int)$_POST['category_id'];
            $cat_query = mysqli_query($conn, "SELECT slug FROM categories WHERE id = $target_id");
            $slug = mysqli_real_escape_string($conn, mysqli_fetch_assoc($cat_query)['slug']);
        } elseif ($type === 'custom') {
            $slug = mysqli_real_escape_string($conn, trim($_POST['custom_slug']));
        }

        if (!empty($slug)) {
            $target_id_sql = $target_id !== null ? $target_id : "NULL";
            mysqli_query($conn, "INSERT INTO menu_items (menu_id, title, type, target_id, slug, `order`) 
                                VALUES ($menu_id, '$title', '$type', $target_id_sql, '$slug', $order)");
        }
        header("Location: menu.php");
        exit;
    } elseif (isset($_POST['update_order'])) {
        $menu_id = (int)$_POST['menu_id'];
        $orders = json_decode($_POST['order'], true);
        if (is_array($orders)) {
            foreach ($orders as $index => $item_id) {
                $item_id = (int)$item_id;
                $index = (int)$index;
                mysqli_query($conn, "UPDATE menu_items SET `order` = $index WHERE id = $item_id AND menu_id = $menu_id");
            }
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid order data']);
        }
        exit;
    }
}

if (isset($_GET['delete_menu'])) {
    $menu_id = (int)$_GET['delete_menu'];
    mysqli_query($conn, "DELETE FROM menu_items WHERE menu_id = $menu_id");
    mysqli_query($conn, "DELETE FROM menus WHERE id = $menu_id");
    header("Location: menu.php");
    exit;
} elseif (isset($_GET['delete_item'])) {
    $item_id = (int)$_GET['delete_item'];
    mysqli_query($conn, "DELETE FROM menu_items WHERE id = $item_id");
    header("Location: menu.php");
    exit;
}
?>

<div class="page-header">
    <div>
        <h1>Menu Yonetimi</h1>
        <p>Site menulerinizi olusturun ve yonetin.</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3><i class="fas fa-plus-circle" style="margin-right: 8px; color: var(--primary);"></i> Yeni Menu Olustur</h3>
    </div>
    <div class="card-body">
        <form method="POST" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group mb-0" style="flex: 1; min-width: 200px;">
                <label class="form-label">Menu Adi</label>
                <input type="text" name="menu_name" class="form-control" placeholder="Menu adi girin" required>
            </div>
            <div class="form-group mb-0" style="width: 180px;">
                <label class="form-label">Menu Turu</label>
                <select name="menu_type" class="form-control" required>
                    <option value="header">Header</option>
                    <option value="footer">Footer</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <button type="submit" name="add_menu" class="btn btn-primary"><i class="fas fa-plus"></i> Menu Ekle</button>
        </form>
    </div>
</div>

<div class="menu-builder">
    <?php if (mysqli_num_rows($menus_result) > 0): ?>
        <?php while ($menu = mysqli_fetch_assoc($menus_result)): ?>
        <div class="menu-card">
            <div class="menu-card-header">
                <h4>
                    <i class="fas fa-bars" style="color: var(--primary);"></i>
                    <?php echo htmlspecialchars($menu['name']); ?>
                    <span class="badge badge-<?php echo $menu['type'] === 'header' ? 'primary' : ($menu['type'] === 'footer' ? 'success' : 'warning'); ?>"><?php echo ucfirst($menu['type']); ?></span>
                </h4>
                <div class="btn-group">
                    <button class="btn btn-secondary btn-sm" onclick="toggleMenuForm(<?php echo $menu['id']; ?>)"><i class="fas fa-plus"></i> Oge Ekle</button>
                    <a href="?delete_menu=<?php echo $menu['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Menuyu silmek istiyor musunuz?')"><i class="fas fa-trash"></i></a>
                </div>
            </div>

            <div id="menuForm-<?php echo $menu['id']; ?>" style="display: none; padding: 16px 20px; border-bottom: 1px solid var(--gray-200); background: var(--gray-50);">
                <form method="POST">
                    <input type="hidden" name="menu_id" value="<?php echo $menu['id']; ?>">
                    <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                        <div class="form-group mb-0" style="flex: 1; min-width: 150px;">
                            <label class="form-label">Baslik</label>
                            <input type="text" name="title" class="form-control" placeholder="Oge basligi" required>
                        </div>
                        <div class="form-group mb-0" style="width: 160px;">
                            <label class="form-label">Tur</label>
                            <select name="type" class="form-control type-select" required onchange="handleTypeChange(this)">
                                <option value="page">Sayfa</option>
                                <option value="category">Kategori</option>
                                <option value="custom">Ozel Baglanti</option>
                            </select>
                        </div>
                        <div class="form-group mb-0 page-field" style="width: 200px;">
                            <label class="form-label">Sayfa</label>
                            <select name="page_id" class="form-control">
                                <?php mysqli_data_seek($pages_result, 0); while ($p = mysqli_fetch_assoc($pages_result)): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group mb-0 category-field" style="width: 200px; display: none;">
                            <label class="form-label">Kategori</label>
                            <select name="category_id" class="form-control">
                                <?php mysqli_data_seek($categories_result, 0); while ($c = mysqli_fetch_assoc($categories_result)): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group mb-0 custom-field" style="width: 200px; display: none;">
                            <label class="form-label">Ozel Slug</label>
                            <input type="text" name="custom_slug" class="form-control" placeholder="ornek-slug">
                        </div>
                        <button type="submit" name="add_menu_item" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Ekle</button>
                    </div>
                </form>
            </div>

            <div class="menu-card-body">
                <?php
                $items_result = mysqli_query($conn, "SELECT * FROM menu_items WHERE menu_id = {$menu['id']} ORDER BY `order`");
                $item_count = mysqli_num_rows($items_result);
                if ($item_count > 0):
                ?>
                <div class="sortable" data-menu-id="<?php echo $menu['id']; ?>">
                    <?php while ($item = mysqli_fetch_assoc($items_result)): ?>
                    <div class="menu-sortable-item" data-item-id="<?php echo $item['id']; ?>">
                        <span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>
                        <span class="item-title"><?php echo htmlspecialchars($item['title']); ?></span>
                        <span class="item-type"><span class="badge badge-secondary"><?php echo $item['type']; ?></span></span>
                        <a href="?delete_item=<?php echo $item['id']; ?>" class="btn btn-ghost btn-sm btn-icon" onclick="return confirm('Ogeyi silmek istiyor musunuz?')" title="Sil"><i class="fas fa-times" style="color: var(--danger);"></i></a>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: var(--gray-400); padding: 20px;">Bu menude henuz oge yok.</p>
                <?php endif; ?>
                <?php mysqli_free_result($items_result); ?>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 48px;">
                <i class="fas fa-bars" style="font-size: 48px; color: var(--gray-300); margin-bottom: 16px;"></i>
                <h4 style="color: var(--gray-500);">Henuz menu bulunmamaktadir.</h4>
                <p style="color: var(--gray-400); margin-bottom: 16px;">Yukaridaki formu kullanarak ilk menunuzu olusturun.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleMenuForm(menuId) {
    var form = document.getElementById('menuForm-' + menuId);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function handleTypeChange(select) {
    var form = select.closest('form');
    form.querySelector('.page-field').style.display = 'none';
    form.querySelector('.category-field').style.display = 'none';
    form.querySelector('.custom-field').style.display = 'none';
    
    if (select.value === 'page') form.querySelector('.page-field').style.display = 'block';
    else if (select.value === 'category') form.querySelector('.category-field').style.display = 'block';
    else if (select.value === 'custom') form.querySelector('.custom-field').style.display = 'block';
}

$(document).ready(function() {
    $('.sortable').sortable({
        handle: '.drag-handle',
        update: function(event, ui) {
            var menuId = $(this).data('menu-id');
            var order = $(this).sortable('toArray', { attribute: 'data-item-id' });
            $.ajax({
                url: 'menu',
                type: 'POST',
                data: { update_order: true, menu_id: menuId, order: JSON.stringify(order) }
            });
        }
    }).disableSelection();
});
</script>

<?php
mysqli_free_result($pages_result);
mysqli_free_result($categories_result);
mysqli_free_result($menus_result);
include 'footer.php';
ob_end_flush();
?>
