<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

global $conn, $base_url;

if (!$conn) {
    ob_end_clean();
    die("Veritabanı bağlantı hatası: " . mysqli_connect_error());
}

$posts_per_page = 6;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $posts_per_page;

$canonical_url = rtrim($base_url, '/') . '/blog';

$seo_title = 'Blog | UMTCar';
$seo_description = 'Lees de laatste artikelen over tweedehands auto\'s, onderhoudstips en autoadvies van UMTCar.';

include __DIR__ . '/../../header.php';

$total_query = "SELECT COUNT(*) as total FROM blogs";
$total_result = mysqli_query($conn, $total_query) or die("Toplam blog sorgu hatası: " . mysqli_error($conn));
$total_posts = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_posts / $posts_per_page);

$query = "SELECT id, name, slug, thumbnail_url, created_at, description FROM blogs ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $query) or die("Sorgu hatası: " . mysqli_error($conn));
mysqli_stmt_bind_param($stmt, "ii", $posts_per_page, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$posts = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
mysqli_free_result($result);

$query_recent = "SELECT name, slug, thumbnail_url FROM blogs ORDER BY created_at DESC LIMIT 3";
$stmt_recent = mysqli_prepare($conn, $query_recent) or die("Son yazılar sorgu hatası: " . mysqli_error($conn));
mysqli_stmt_execute($stmt_recent);
$result_recent = mysqli_stmt_get_result($stmt_recent);
$recent_posts = mysqli_fetch_all($result_recent, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_recent);
mysqli_free_result($result_recent);
ob_end_flush();
?>

<div class="vehica-blog__inner">
    <div class="vehica-blog__content">
        <div class="vehica-posts vehica-posts--v2 vehica-grid vehica-posts--archive">
            <?php if (!empty($posts)): ?>
                <?php foreach ($posts as $post): ?>
                    <div class="vehica-blog-card">
                        <article class="vehica-blog-card__inner">
                            <a href="<?php echo htmlspecialchars($base_url . $post['slug']); ?>" title="<?php echo htmlspecialchars($post['name']); ?>" class="vehica-blog-card__image-static">
                                <img src="<?php echo htmlspecialchars($post['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($post['name']); ?>">
                            </a>
                            <div class="vehica-blog-card__content">
                                <h3>
                                    <a href="<?php echo htmlspecialchars($base_url . $post['slug']); ?>" title="<?php echo htmlspecialchars($post['name']); ?>" class="vehica-blog-card__title">
                                        <?php echo htmlspecialchars($post['name']); ?>
                                    </a>
                                </h3>
                                <div class="vehica-blog-card__content__top">
                                    <div class="vehica-blog-card__author">
                                        <div class="vehica-blog-card__author__name">
                                            <span class="material-icons-round">person</span>
                                            <span>Umtcar Team</span>
                                        </div>
                                    </div>
                                    <div class="vehica-blog-card__date">
                                        <span class="material-icons-round">calendar_today</span>
                                        <span><?php echo date('d F Y', strtotime($post['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="vehica-blog-card__excerpt">
                                    <?php echo substr(strip_tags($post['description']), 0, 150) . '...'; ?>
                                </div>
                                <div>
                                    <a class="vehica-button" href="<?php echo htmlspecialchars($base_url . '/' . $post['slug']); ?>" title="<?php echo htmlspecialchars($post['name']); ?>">
                                        Lees Meer
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vehica-no-results-search">
                    <h3>Geen artikelen gevonden!</h3>
                    <h4>Probeer een andere zoekopdracht:</h4>
                </div>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <div class="vehica-pagination">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li><a href="?page=<?php echo $page - 1; ?>" class="page-link">&laquo; Vorige</a></li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li><a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <li><a href="?page=<?php echo $page + 1; ?>" class="page-link">Volgende &raquo;</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="vehica-blog__sidebar">
        <div class="widget">
            <div class="spacer"></div>
    <?php
    include __DIR__ . '/sidebar.php'; 
    ?>
       
	   </div>
    </div>
	
</div>


<?php include __DIR__ . '/../../footer.php'; ?>
