<?php
ob_start();
require_once __DIR__ . '/../database.php';

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

include __DIR__ . '/../../hatalar.php';

require_once __DIR__ . '/post-functions.php';

$edit_mode = false;
$post = [
    'id' => '',
    'title' => '',
    'content' => '',
    'slug' => '',
    'meta_title' => '',
    'meta_description' => '',
    'featured_image' => '',
    'category_id' => '',
    'status' => 'draft'
];
$post_tags = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $edit_mode = true;
    $post_id = (int)$_GET['id'];

    $query = "SELECT * FROM posts WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $post_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $post = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$post) {
        $_SESSION['error'] = "Yazi bulunamadi.";
        header('Location: ' . BASE_URL . '/admin/post.php');
        exit;
    }

    $tag_query = "SELECT t.name FROM tags t JOIN post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = ?";
    $tag_stmt = mysqli_prepare($conn, $tag_query);
    mysqli_stmt_bind_param($tag_stmt, 'i', $post_id);
    mysqli_stmt_execute($tag_stmt);
    $tag_result = mysqli_stmt_get_result($tag_stmt);
    while ($tag_row = mysqli_fetch_assoc($tag_result)) {
        $post_tags[] = $tag_row['name'];
    }
    mysqli_stmt_close($tag_stmt);
}

$categories = [];
$cat_result = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");
if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $categories[] = $row;
    }
    mysqli_free_result($cat_result);
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_post'])) {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $slug = trim($_POST['slug'] ?? '');
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $status = $_POST['status'] ?? 'draft';
    $tags_input = trim($_POST['tags'] ?? '');

    if (empty($title)) $errors[] = "Baslik zorunludur.";

    if (empty($slug)) {
        $slug = strtolower(str_replace([' ', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'], ['-', 'i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c'], $title));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/^-|-$/', '', $slug);
    }

    $featured_image = $post['featured_image'] ?? '';
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadFeaturedImage($_FILES['featured_image'], $conn);
        if ($upload_result['success']) {
            $featured_image = $upload_result['path'];
        } else {
            $errors[] = $upload_result['error'];
        }
    }

    if (isset($_POST['remove_featured_image'])) {
        $featured_image = '';
    }

    if (empty($errors)) {
        if ($edit_mode) {
            $query = "UPDATE posts SET title=?, content=?, slug=?, meta_title=?, meta_description=?, featured_image=?, category_id=?, status=?, updated_at=NOW() WHERE id=?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssssssisi', $title, $content, $slug, $meta_title, $meta_description, $featured_image, $category_id, $status, $post_id);
        } else {
            $query = "INSERT INTO posts (title, content, slug, meta_title, meta_description, featured_image, category_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssssssis', $title, $content, $slug, $meta_title, $meta_description, $featured_image, $category_id, $status);
        }

        if (mysqli_stmt_execute($stmt)) {
            $current_post_id = $edit_mode ? $post_id : mysqli_insert_id($conn);

            mysqli_query($conn, "DELETE FROM post_tags WHERE post_id = $current_post_id");
            if (!empty($tags_input)) {
                $tag_names = array_map('trim', explode(',', $tags_input));
                foreach ($tag_names as $tag_name) {
                    if (empty($tag_name)) continue;
                    $tag_slug = strtolower(str_replace([' ', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['-', 'i', 'g', 'u', 's', 'o', 'c'], $tag_name));
                    $tag_slug = preg_replace('/[^a-z0-9-]+/', '-', $tag_slug);
                    $tag_slug = preg_replace('/^-|-$/', '', $tag_slug);

                    $check = mysqli_query($conn, "SELECT id FROM tags WHERE name = '" . mysqli_real_escape_string($conn, $tag_name) . "'");
                    if ($tag_row = mysqli_fetch_assoc($check)) {
                        $tag_id = $tag_row['id'];
                    } else {
                        mysqli_query($conn, "INSERT INTO tags (name, slug, created_at) VALUES ('" . mysqli_real_escape_string($conn, $tag_name) . "', '" . mysqli_real_escape_string($conn, $tag_slug) . "', NOW())");
                        $tag_id = mysqli_insert_id($conn);
                    }
                    mysqli_query($conn, "INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES ($current_post_id, $tag_id)");
                }
            }

            $_SESSION['success'] = $edit_mode ? "Yazi guncellendi." : "Yazi olusturuldu.";
            header('Location: ' . BASE_URL . '/admin/includes/post/add-post?id=' . $current_post_id);
            exit;
        } else {
            $errors[] = "Veritabani hatasi: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

include __DIR__ . '/../../header.php';
?>

<div class="page-header">
    <div>
        <h1><?php echo $edit_mode ? 'Yaziyi Duzenle' : 'Yeni Yazi Ekle'; ?></h1>
        <p><?php echo $edit_mode ? 'Mevcut yaziyi duzenleyin.' : 'Yeni bir blog yazisi olusturun.'; ?></p>
    </div>
    <div class="btn-group">
        <a href="<?php echo BASE_URL; ?>/admin/post" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Geri Don</a>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php foreach ($errors as $error): ?>
            <span><?php echo htmlspecialchars($error); ?></span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="postForm">
<div style="display: grid; grid-template-columns: 1fr 340px; gap: 20px;">
    <div>
        <div class="card mb-3">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Baslik <span class="required">*</span></label>
                    <input type="text" name="title" id="postTitle" class="form-control" value="<?php echo htmlspecialchars($post['title']); ?>" placeholder="Yazi basligini girin" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" id="postSlug" class="form-control" value="<?php echo htmlspecialchars($post['slug']); ?>" placeholder="Otomatik olusturulur">
                    <span class="form-hint">Bos birakirsaniz basliktan otomatik olusturulur.</span>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-edit" style="margin-right: 8px; color: var(--primary);"></i> Icerik</h3>
                <div class="btn-group">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openMediaLibrary('editor')"><i class="fas fa-images"></i> Galeri</button>
                    <button type="button" class="btn btn-info btn-sm" id="togglePreview"><i class="fas fa-eye"></i> Onizleme</button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="editor-container">
                    <textarea name="content" id="postContent"><?php echo htmlspecialchars($post['content']); ?></textarea>
                </div>
                <div class="preview-panel" id="previewPanel">
                    <div id="previewContent"></div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-search" style="margin-right: 8px; color: var(--warning);"></i> SEO Ayarlari</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Meta Baslik</label>
                    <input type="text" name="meta_title" class="form-control" value="<?php echo htmlspecialchars($post['meta_title']); ?>" placeholder="SEO basligini girin">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Meta Aciklama</label>
                    <textarea name="meta_description" class="form-control" rows="3" placeholder="SEO aciklamasini girin"><?php echo htmlspecialchars($post['meta_description']); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="card mb-3">
            <div class="card-header">
                <h3>Yayinla</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Durum</label>
                    <select name="status" class="form-control">
                        <option value="draft" <?php echo ($post['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Taslak</option>
                        <option value="published" <?php echo ($post['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Yayinda</option>
                    </select>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" name="save_post" class="btn btn-primary btn-block"><i class="fas fa-save"></i> <?php echo $edit_mode ? 'Guncelle' : 'Kaydet'; ?></button>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3>Kategori</h3>
            </div>
            <div class="card-body">
                <select name="category_id" class="form-control">
                    <option value="">Kategori Sec</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo ($post['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3>One Cikan Gorsel</h3>
            </div>
            <div class="card-body">
                <div id="featuredImagePreview">
                    <?php if (!empty($post['featured_image'])): ?>
                    <div style="margin-bottom: 12px;">
                        <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($post['featured_image']); ?>" style="width: 100%; border-radius: 8px; border: 1px solid var(--gray-200);">
                    </div>
                    <button type="submit" name="remove_featured_image" class="btn btn-danger btn-sm btn-block"><i class="fas fa-trash"></i> Gorseli Kaldir</button>
                    <?php endif; ?>
                </div>
                <div class="file-upload mt-1" id="featuredUpload">
                    <input type="file" name="featured_image" accept="image/*" onchange="previewFeaturedImage(this)">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Gorsel yuklemek icin tiklayin</p>
                </div>
                <div style="text-align: center; margin-top: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openMediaLibrary('featured')"><i class="fas fa-images"></i> Galeriden Sec</button>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3>Etiketler</h3>
            </div>
            <div class="card-body">
                <div class="form-group mb-0">
                    <input type="text" name="tags" id="tagsInput" class="form-control" value="<?php echo htmlspecialchars(implode(', ', $post_tags)); ?>" placeholder="Etiketleri virgul ile ayirin">
                    <span class="form-hint">Ornek: PHP, Web Gelistirme, Laravel</span>
                </div>
                <div id="tagSuggestions" style="margin-top: 8px;"></div>
            </div>
        </div>
    </div>
</div>
</form>

<!-- WordPress-Style Media Library Modal -->
<div class="media-library-overlay" id="mediaLibraryOverlay">
    <div class="media-library">
        <div class="media-library-header">
            <h3><i class="fas fa-images" style="margin-right: 8px; color: var(--primary);"></i> Medya Kutuphanesi</h3>
            <button class="media-library-close" onclick="closeMediaLibrary()">&times;</button>
        </div>
        <div class="media-library-tabs">
            <button class="media-tab active" data-tab="library" onclick="switchMediaTab('library', this)">Kutuphaneden Sec</button>
            <button class="media-tab" data-tab="upload" onclick="switchMediaTab('upload', this)">Yeni Yukle</button>
        </div>
        <div class="media-library-body">
            <div class="media-grid-container">
                <div id="mediaUploadTab" style="display: none;">
                    <div class="media-upload-zone" id="mediaUploadZone">
                        <input type="file" id="mediaFileInput" accept="image/*" multiple onchange="handleMediaUpload(this)">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h4>Gorsel Yukle</h4>
                        <p>Dosyalari suruklein veya tiklayin</p>
                    </div>
                    <div id="uploadProgress" style="display: none;">
                        <div style="background: var(--gray-200); border-radius: 8px; overflow: hidden; height: 8px;">
                            <div id="uploadProgressBar" style="width: 0%; height: 100%; background: var(--primary); transition: width 0.3s;"></div>
                        </div>
                        <p style="text-align: center; margin-top: 8px; font-size: 13px; color: var(--gray-500);">Yukleniyor...</p>
                    </div>
                </div>
                <div id="mediaLibraryTab">
                    <div class="media-grid" id="mediaGrid">
                        <p style="text-align: center; color: var(--gray-400); grid-column: 1/-1; padding: 40px;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>
                            Gorseller yukleniyor...
                        </p>
                    </div>
                </div>
            </div>
            <div class="media-sidebar" id="mediaSidebar">
                <h4>Gorsel Detaylari</h4>
                <img id="mediaPreviewImg" class="media-preview-img" src="" alt="">
                <ul class="media-detail-list">
                    <li><span>Dosya:</span> <span id="mediaFileName">-</span></li>
                    <li><span>Boyut:</span> <span id="mediaFileSize">-</span></li>
                </ul>
            </div>
        </div>
        <div class="media-library-footer">
            <span id="mediaSelectedCount" style="font-size: 13px; color: var(--gray-500);">0 gorsel secili</span>
            <div class="btn-group">
                <button class="btn btn-secondary" onclick="closeMediaLibrary()">Iptal</button>
                <button class="btn btn-primary" id="mediaInsertBtn" onclick="insertSelectedMedia()" disabled>Gorsel Ekle</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
var mediaTarget = 'editor';
var selectedMedia = [];

tinymce.init({
    selector: '#postContent',
    height: 500,
    menubar: 'file edit view insert format tools table',
    plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
    toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | gallery_btn preview_btn | removeformat | code fullscreen help',
    content_style: 'body { font-family: Inter, -apple-system, sans-serif; font-size: 15px; line-height: 1.7; color: #334155; }',
    images_upload_url: '<?php echo BASE_URL; ?>/admin/includes/post/upload_image',
    automatic_uploads: true,
    file_picker_types: 'image',
    images_upload_handler: function(blobInfo, progress) {
        return new Promise(function(resolve, reject) {
            var formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo BASE_URL; ?>/admin/includes/post/upload_image');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var json = JSON.parse(xhr.responseText);
                    if (json.location) {
                        resolve(json.location);
                    } else {
                        reject('Gorsel yuklenirken hata olustu');
                    }
                } else {
                    reject('HTTP Hata: ' + xhr.status);
                }
            };
            xhr.onerror = function() { reject('Baglanti hatasi'); };
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) progress(e.loaded / e.total * 100);
            };
            xhr.send(formData);
        });
    },
    setup: function(editor) {
        editor.ui.registry.addButton('gallery_btn', {
            icon: 'gallery',
            tooltip: 'Medya Kutuphanesi',
            onAction: function() {
                openMediaLibrary('editor');
            }
        });
        editor.ui.registry.addButton('preview_btn', {
            icon: 'preview',
            tooltip: 'Gelismis Onizleme',
            onAction: function() {
                toggleAdvancedPreview();
            }
        });
    }
});

function toggleAdvancedPreview() {
    var panel = document.getElementById('previewPanel');
    var content = document.getElementById('previewContent');
    var isShowing = panel.classList.contains('show');

    if (isShowing) {
        panel.classList.remove('show');
        document.getElementById('togglePreview').innerHTML = '<i class="fas fa-eye"></i> Onizleme';
    } else {
        var editorContent = tinymce.get('postContent').getContent();
        var title = document.getElementById('postTitle').value;
        content.innerHTML = '<h1 style="font-size: 28px; font-weight: 700; margin-bottom: 16px; color: #0f172a;">' + (title || 'Baslık Yok') + '</h1><hr style="border: none; border-top: 1px solid #e2e8f0; margin: 16px 0;">' + editorContent;
        panel.classList.add('show');
        document.getElementById('togglePreview').innerHTML = '<i class="fas fa-edit"></i> Editore Don';
    }
}

document.getElementById('togglePreview').addEventListener('click', function() {
    toggleAdvancedPreview();
});

function openMediaLibrary(target) {
    mediaTarget = target;
    selectedMedia = [];
    updateMediaSelection();
    loadMediaLibrary();
    document.getElementById('mediaLibraryOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeMediaLibrary() {
    document.getElementById('mediaLibraryOverlay').classList.remove('show');
    document.body.style.overflow = '';
}

function switchMediaTab(tab, btn) {
    document.querySelectorAll('.media-tab').forEach(function(t) { t.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById('mediaUploadTab').style.display = tab === 'upload' ? 'block' : 'none';
    document.getElementById('mediaLibraryTab').style.display = tab === 'library' ? 'block' : 'none';
}

function loadMediaLibrary() {
    var grid = document.getElementById('mediaGrid');
    grid.innerHTML = '<p style="text-align: center; color: var(--gray-400); grid-column: 1/-1; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>Gorseller yukleniyor...</p>';

    var xhr = new XMLHttpRequest();
    xhr.open('GET', '<?php echo BASE_URL; ?>/admin/includes/post/upload_image?action=list');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var images = JSON.parse(xhr.responseText);
                renderMediaGrid(images);
            } catch (e) {
                grid.innerHTML = '<p style="text-align: center; color: var(--gray-400); grid-column: 1/-1; padding: 40px;"><i class="fas fa-images" style="font-size: 48px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>Henuz gorsel yok. "Yeni Yukle" sekmesinden gorsel yukleyin.</p>';
            }
        }
    };
    xhr.onerror = function() {
        grid.innerHTML = '<p style="text-align: center; color: var(--gray-400); grid-column: 1/-1; padding: 40px;">Gorseller yuklenirken hata olustu.</p>';
    };
    xhr.send();
}

function renderMediaGrid(images) {
    var grid = document.getElementById('mediaGrid');
    if (!images || images.length === 0) {
        grid.innerHTML = '<p style="text-align: center; color: var(--gray-400); grid-column: 1/-1; padding: 40px;"><i class="fas fa-images" style="font-size: 48px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>Henuz gorsel yok.</p>';
        return;
    }
    grid.innerHTML = '';
    images.forEach(function(img) {
        var div = document.createElement('div');
        div.className = 'media-item';
        div.setAttribute('data-url', img.url);
        div.setAttribute('data-name', img.name);
        div.innerHTML = '<img src="' + img.url + '" alt="' + img.name + '" loading="lazy"><div class="media-item-info">' + img.name + '</div>';
        div.addEventListener('click', function() {
            toggleMediaSelection(this);
        });
        grid.appendChild(div);
    });
}

function toggleMediaSelection(item) {
    var url = item.getAttribute('data-url');
    var name = item.getAttribute('data-name');
    var index = selectedMedia.findIndex(function(m) { return m.url === url; });

    if (index > -1) {
        selectedMedia.splice(index, 1);
        item.classList.remove('selected');
    } else {
        if (mediaTarget === 'featured') {
            selectedMedia = [];
            document.querySelectorAll('.media-item.selected').forEach(function(el) { el.classList.remove('selected'); });
        }
        selectedMedia.push({ url: url, name: name });
        item.classList.add('selected');
    }

    updateMediaSelection();

    var sidebar = document.getElementById('mediaSidebar');
    if (selectedMedia.length > 0) {
        var last = selectedMedia[selectedMedia.length - 1];
        document.getElementById('mediaPreviewImg').src = last.url;
        document.getElementById('mediaFileName').textContent = last.name;
        sidebar.classList.add('show');
    } else {
        sidebar.classList.remove('show');
    }
}

function updateMediaSelection() {
    document.getElementById('mediaSelectedCount').textContent = selectedMedia.length + ' gorsel secili';
    document.getElementById('mediaInsertBtn').disabled = selectedMedia.length === 0;
}

function insertSelectedMedia() {
    if (selectedMedia.length === 0) return;

    if (mediaTarget === 'editor') {
        var html = '';
        selectedMedia.forEach(function(m) {
            html += '<p><img src="' + m.url + '" alt="' + m.name + '" style="max-width: 100%; height: auto;" /></p>';
        });
        tinymce.get('postContent').insertContent(html);
    } else if (mediaTarget === 'featured') {
        var imgUrl = selectedMedia[0].url;
        var relativePath = imgUrl.replace('<?php echo BASE_URL; ?>/', '');
        var preview = document.getElementById('featuredImagePreview');
        preview.innerHTML = '<div style="margin-bottom: 12px;"><img src="' + imgUrl + '" style="width: 100%; border-radius: 8px; border: 1px solid var(--gray-200);"></div>';

        var hiddenInput = document.querySelector('input[name="featured_image_url"]');
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'featured_image_url';
            document.getElementById('postForm').appendChild(hiddenInput);
        }
        hiddenInput.value = relativePath;
    }
    closeMediaLibrary();
}

function handleMediaUpload(input) {
    if (!input.files || input.files.length === 0) return;

    var progressDiv = document.getElementById('uploadProgress');
    var progressBar = document.getElementById('uploadProgressBar');
    progressDiv.style.display = 'block';

    var files = Array.from(input.files);
    var uploaded = 0;

    files.forEach(function(file) {
        var formData = new FormData();
        formData.append('file', file);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo BASE_URL; ?>/admin/includes/post/upload_image');
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                var pct = ((uploaded / files.length) * 100) + ((e.loaded / e.total) * (100 / files.length));
                progressBar.style.width = pct + '%';
            }
        };
        xhr.onload = function() {
            uploaded++;
            progressBar.style.width = (uploaded / files.length * 100) + '%';
            if (uploaded === files.length) {
                setTimeout(function() {
                    progressDiv.style.display = 'none';
                    progressBar.style.width = '0%';
                    switchMediaTab('library', document.querySelector('[data-tab="library"]'));
                    loadMediaLibrary();
                }, 500);
            }
        };
        xhr.send(formData);
    });
    input.value = '';
}

function previewFeaturedImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('featuredImagePreview').innerHTML = '<div style="margin-bottom: 12px;"><img src="' + e.target.result + '" style="width: 100%; border-radius: 8px; border: 1px solid var(--gray-200);"></div>';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('postTitle').addEventListener('input', function() {
    var slugField = document.getElementById('postSlug');
    if (!slugField.value || slugField.value === slugField.dataset.lastGenerated) {
        var slug = this.value.toLowerCase()
            .replace(/[ı]/g, 'i').replace(/[ğ]/g, 'g').replace(/[ü]/g, 'u')
            .replace(/[ş]/g, 's').replace(/[ö]/g, 'o').replace(/[ç]/g, 'c')
            .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        slugField.value = slug;
        slugField.dataset.lastGenerated = slug;
    }
});

var tagInput = document.getElementById('tagsInput');
var tagDebounce;
tagInput.addEventListener('input', function() {
    clearTimeout(tagDebounce);
    var val = this.value;
    var lastComma = val.lastIndexOf(',');
    var term = (lastComma > -1 ? val.substring(lastComma + 1) : val).trim();
    if (term.length < 2) { document.getElementById('tagSuggestions').innerHTML = ''; return; }

    tagDebounce = setTimeout(function() {
        var formData = new FormData();
        formData.append('action', 'get_tag_suggestions');
        formData.append('term', term);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo BASE_URL; ?>/admin/includes/post/tag_suggestions');
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var tags = JSON.parse(xhr.responseText);
                    var html = '';
                    tags.forEach(function(t) {
                        html += '<span class="badge badge-secondary" style="cursor: pointer; margin: 2px;" onclick="addTag(\'' + t.replace(/'/g, "\\'") + '\')">' + t + '</span>';
                    });
                    document.getElementById('tagSuggestions').innerHTML = html;
                } catch(e) {}
            }
        };
        xhr.send(formData);
    }, 300);
});

function addTag(tag) {
    var input = document.getElementById('tagsInput');
    var val = input.value;
    var lastComma = val.lastIndexOf(',');
    if (lastComma > -1) {
        input.value = val.substring(0, lastComma + 1) + ' ' + tag + ', ';
    } else {
        input.value = tag + ', ';
    }
    document.getElementById('tagSuggestions').innerHTML = '';
    input.focus();
}

var uploadZone = document.getElementById('mediaUploadZone');
if (uploadZone) {
    ['dragenter', 'dragover'].forEach(function(evt) {
        uploadZone.addEventListener(evt, function(e) { e.preventDefault(); this.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(function(evt) {
        uploadZone.addEventListener(evt, function(e) { e.preventDefault(); this.classList.remove('dragover'); });
    });
    uploadZone.addEventListener('drop', function(e) {
        var input = document.getElementById('mediaFileInput');
        input.files = e.dataTransfer.files;
        handleMediaUpload(input);
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMediaLibrary();
});
</script>

<?php
include __DIR__ . '/../../footer.php';
ob_end_flush();
?>
