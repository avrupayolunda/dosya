<?php
require_once __DIR__ . '/includes/database.php';
include 'hatalar.php';
include 'header.php';

$sql = "SELECT id, title, created_at FROM pages ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<div class="page-header">
    <div>
        <h1>Sayfalar</h1>
        <p>Statik sayfalarinizi buradan yonetin.</p>
    </div>
    <a href="includes/page/add-page" class="btn btn-primary"><i class="fas fa-plus"></i> Yeni Sayfa Ekle</a>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Baslik</th>
                        <th>Olusturulma Tarihi</th>
                        <th style="text-align: right;">Islemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td>
                                <a href="includes/page/add-page?id=<?php echo $row['id']; ?>" style="color: var(--gray-900); font-weight: 500;">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </a>
                            </td>
                            <td style="white-space: nowrap;"><?php echo date('d.m.Y H:i', strtotime($row['created_at'])); ?></td>
                            <td style="text-align: right;">
                                <div class="btn-group">
                                    <a href="includes/page/add-page?id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Duzenle</a>
                                    <a href="includes/page/delete-page?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu sayfayi silmek istediginizden emin misiniz?');"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="table-empty">
                                <i class="fas fa-inbox"></i>
                                Henuz sayfa bulunmamaktadir.
                                <br><br>
                                <a href="includes/page/add-page" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Ilk Sayfayi Olustur</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
mysqli_free_result($result);
include 'footer.php';
?>
