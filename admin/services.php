<?php
require __DIR__ . '/inc/auth.php';
$config = require __DIR__ . '/../config.php';
$db = $config['db'];
$mysqli = new mysqli($db['host'],$db['user'],$db['pass'],$db['name']);
$err = $msg = '';

// Endpoint AJAX para reordenar (actualiza columna Orden)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
  $payload = json_decode(file_get_contents('php://input'), true) ?: [];
  if (($payload['action'] ?? '') === 'reorder') {
    header('Content-Type: application/json; charset=utf-8');
    if (!check_csrf($payload['csrf'] ?? '')) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'CSRF inválido']);
      exit;
    }
    $order = $payload['order'] ?? [];
    if (!is_array($order) || empty($order)) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Orden vacío']);
      exit;
    }
    // Sanitizar a enteros únicos
    $ids = array_values(array_unique(array_map('intval', $order)));
    try {
      $mysqli->begin_transaction();
      $stmt = $mysqli->prepare('UPDATE Servicios SET Orden=? WHERE Id=?');
      $pos = 1;
      foreach ($ids as $id) {
        $stmt->bind_param('ii', $pos, $id);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        $pos++;
      }
      $stmt->close();
      $mysqli->commit();
      echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
      $mysqli->rollback();
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'DB: '.$e->getMessage()]);
    }
    exit;
  }
}

// manejar acciones POST: create / update / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!check_csrf($_POST['csrf'] ?? '')) { $err = 'Token CSRF inválido.'; }
  else {
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
      $titulo = trim($_POST['titulo'] ?? '');
      $desc   = trim($_POST['descripcion'] ?? '');
      $orden  = intval($_POST['orden'] ?? 0);
      // manejar imagen subida (opcional)
      $imageName = null;
      if (!empty($_FILES['image']['name'])) {
        $tmp = $_FILES['image']['tmp_name'];
        $safe = basename($_FILES['image']['name']);
        $dest = __DIR__ . '/../img/' . $safe;
        if (move_uploaded_file($tmp, $dest)) $imageName = $safe;
      }
      $stmt = $mysqli->prepare("INSERT INTO Servicios (Titulo, Descripcion, Image, Activo, Orden) VALUES (?, ?, ?, 1, ?)");
      $stmt->bind_param("sssi", $titulo, $desc, $imageName, $orden);
      if ($stmt->execute()) $msg = 'Servicio creado.'; else $err = $mysqli->error;
      $stmt->close();
    }
    elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
      $id     = intval($_POST['id'] ?? 0);
      $titulo = trim($_POST['titulo'] ?? '');
      $desc   = trim($_POST['descripcion'] ?? '');
      $orden  = intval($_POST['orden'] ?? 0);
      // imagen opcional
      if (!empty($_FILES['image']['name'])) {
        $tmp = $_FILES['image']['tmp_name'];
        $safe = basename($_FILES['image']['name']);
        $dest = __DIR__ . '/../img/' . $safe;
        if (move_uploaded_file($tmp, $dest)) {
          $stmt = $mysqli->prepare("UPDATE Servicios SET Titulo=?, Descripcion=?, Image=?, Orden=? WHERE Id=?");
          $stmt->bind_param("sssii", $titulo, $desc, $safe, $orden, $id);
        }
      } else {
        $stmt = $mysqli->prepare("UPDATE Servicios SET Titulo=?, Descripcion=?, Orden=? WHERE Id=?");
        $stmt->bind_param("ssii", $titulo, $desc, $orden, $id);
      }
      if ($stmt->execute()) $msg = 'Servicio actualizado.'; else $err = $mysqli->error;
      $stmt->close();
    }
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
      $id = intval($_POST['id'] ?? 0);
      $stmt = $mysqli->prepare("DELETE FROM Servicios WHERE Id = ?");
      $stmt->bind_param("i",$id);
      if ($stmt->execute()) $msg = 'Servicio eliminado.'; else $err = $mysqli->error;
      $stmt->close();
    }
  }
}

// obtener lista (incluir columna Orden y ordenar por Orden)
$res = $mysqli->query("SELECT Id, Titulo, Descripcion, Image, Activo, Orden FROM servicios ORDER BY Orden ASC, Id DESC");
$servicios = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$csrf = $_SESSION['csrf_token'];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Administrar servicios</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .wrap{max-width:1200px;margin:24px auto;padding:18px;background:#fff;border-radius:12px}
    .top-actions{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;align-items:center}
    .services-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
    .service-card{background:#fafafa;border-radius:12px;padding:16px;box-shadow:0 8px 20px rgba(10,20,30,0.06);display:flex;flex-direction:column;gap:12px;min-height:160px}
    .service-card img{width:100%;height:140px;object-fit:cover;border-radius:8px}
    .service-head{display:flex;align-items:center;gap:12px}
    .service-meta{flex:1}
    .service-title{font-size:1.05rem;font-weight:800;margin:0 0 6px 0;color:var(--color-azul-oscuro)}
    .service-desc{font-size:0.95rem;color:#46575f;max-height:72px;overflow:hidden;text-overflow:ellipsis}
    .service-footer{display:flex;gap:8px;align-items:center;justify-content:space-between;margin-top:auto}
    .btn-sm{padding:8px 10px;border-radius:8px;border:none;cursor:pointer;font-weight:700}
    .btn-edit{background:#fff;border:1px solid #e6e9eb;color:var(--color-azul-oscuro)}
    .btn-delete{background:#ff5c5c;color:#fff}
    .badge{display:inline-block;padding:6px 8px;border-radius:8px;font-weight:700;background:rgba(0,0,0,0.05)}
    .form-inline{display:flex;flex-direction:column;gap:8px;margin-top:10px;background:#fff;padding:12px;border-radius:8px;border:1px solid #eee}
    .form-inline input[type="text"], .form-inline textarea, .form-inline input[type="number"]{width:100%;padding:8px;border:1px solid #e6e9eb;border-radius:8px}
    .small-muted{font-size:0.9rem;color:#7d8b90}
    .hidden{display:none}
    /* Estilos de arrastre */
    .service-card{cursor:grab}
    .service-card:active{cursor:grabbing}
    .service-card.dragging{opacity:0.6; transform:scale(0.995)}
    .drop-indicator{height:8px;background:#ffb30033;border-radius:4px;margin:-6px 0 6px 0;display:none}
    .drop-indicator.show{display:block}
    @media (max-width:700px){ .service-card img{height:120px} .service-desc{max-height:54px} }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Servicios</h1>
    <div class="top-actions">
      <a class="btn" href="dashboard.php">← Volver</a>
      <a class="btn" href="logout.php">Cerrar sesión</a>
      <div style="flex:1"></div>
      <?php if ($err) echo "<div style='color:#b00020'>$err</div>"; ?>
      <?php if ($msg) echo "<div style='color:green'>$msg</div>"; ?>
    </div>

    <section style="margin-bottom:18px;">
      <h2 style="margin:0 0 8px 0">Crear nuevo servicio</h2>
      <form method="post" enctype="multipart/form-data" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="action" value="create">
        <input name="titulo" placeholder="Título" required style="flex:1;min-width:220px;padding:10px;border-radius:8px;border:1px solid #e6e9eb">
        <input name="orden" type="number" placeholder="Orden (número)" style="width:120px;padding:10px;border-radius:8px;border:1px solid #e6e9eb">
        <input type="file" name="image" accept="image/*">
        <textarea name="descripcion" placeholder="Descripción (opcional)" style="flex-basis:100%;min-height:80px;padding:10px;border-radius:8px;border:1px solid #e6e9eb"></textarea>
        <div style="width:100%;text-align:right"><button class="btn btn-orange" type="submit">Crear servicio</button></div>
      </form>
    </section>

    <hr>

    <h2 style="margin-top:18px">Servicios existentes</h2>
    <div id="reorderStatus" class="small-muted" style="display:none; margin:6px 0 10px;">Guardando nuevo orden…</div>
    <div class="services-grid" id="servicesGrid" data-csrf="<?php echo htmlspecialchars($csrf); ?>">
      <?php foreach($servicios as $s): ?>
        <article class="service-card" data-id="<?php echo $s['Id']; ?>" draggable="true">
          <?php if(!empty($s['Image'])): ?>
            <img src="../img/<?php echo htmlspecialchars($s['Image']); ?>" alt="<?php echo htmlspecialchars($s['Titulo']); ?>">
          <?php else: ?>
            <div style="height:140px;border-radius:8px;background:linear-gradient(180deg,#f0f2f4,#eef2f4);display:flex;align-items:center;justify-content:center;color:#93a3a9">Sin imagen</div>
          <?php endif; ?>

          <div class="service-head">
            <div class="service-meta">
              <h3 class="service-title"><?php echo htmlspecialchars($s['Titulo']); ?></h3>
              <div class="small-muted js-meta">ID: <?php echo $s['Id']; ?> — Orden: <span class="js-order-num"><?php echo intval($s['Orden'] ?? 0); ?></span> — <?php echo $s['Activo'] ? '<span class="badge">Activo</span>' : '<span class="badge" style="opacity:.6">Inactivo</span>'; ?></div>
            </div>
          </div>

          <div class="service-desc"><?php echo nl2br(htmlspecialchars(substr($s['Descripcion'] ?? '',0,320))); ?></div>

          <div class="service-footer">
            <div style="display:flex;gap:8px;align-items:center">
              <button class="btn-sm btn-edit js-toggle-edit" type="button" data-id="<?php echo $s['Id']; ?>">Editar</button>
              <form method="post" style="display:inline" onsubmit="return confirm('Eliminar servicio?');">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $s['Id']; ?>">
                <button class="btn-sm btn-delete" type="submit">Eliminar</button>
              </form>
            </div>

          </div>

          <!-- inline edit form (hidden by default) -->
          <div class="form-inline hidden js-edit-form" id="edit-<?php echo $s['Id']; ?>">
            <form method="post" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:8px">
              <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?php echo $s['Id']; ?>">

              <label>Título</label>
              <input name="titulo" value="<?php echo htmlspecialchars($s['Titulo']); ?>">

              <label>Orden</label>
              <input name="orden" type="number" value="<?php echo intval($s['Orden'] ?? 0); ?>">

              <label>Descripción</label>
              <textarea name="descripcion"><?php echo htmlspecialchars($s['Descripcion']); ?></textarea>

              <label>Imagen (opcional)</label>
              <input type="file" name="image" accept="image/*">

              <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn-sm btn-edit js-cancel-edit" data-id="<?php echo $s['Id']; ?>">Cancelar</button>
                <button class="btn-sm btn" type="submit">Guardar</button>
              </div>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
    // toggle inline edit forms
    document.querySelectorAll('.js-toggle-edit').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const id = btn.getAttribute('data-id');
        const form = document.getElementById('edit-' + id);
        if (!form) return;
        form.classList.toggle('hidden');
        // scroll into view when opened
        if (!form.classList.contains('hidden')) form.scrollIntoView({behavior:'smooth', block:'center'});
      });
    });
    document.querySelectorAll('.js-cancel-edit').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const id = btn.getAttribute('data-id');
        const form = document.getElementById('edit-' + id);
        if (form) form.classList.add('hidden');
      });
    });

    // Drag & Drop reordenamiento
    (function(){
      const grid = document.getElementById('servicesGrid');
      if (!grid) return;
      const status = document.getElementById('reorderStatus');
      const csrf = grid.getAttribute('data-csrf');

      const getDraggables = () => Array.from(grid.querySelectorAll('article.service-card'));

      const getDragAfterElement = (container, y) => {
        const els = getDraggables().filter(el => !el.classList.contains('dragging'));
        let closest = { offset: Number.NEGATIVE_INFINITY, element: null };
        els.forEach(el => {
          const box = el.getBoundingClientRect();
          const offset = y - box.top - box.height / 2;
          if (offset < 0 && offset > closest.offset) {
            closest = { offset, element: el };
          }
        });
        return closest.element;
      };

      grid.addEventListener('dragstart', (e) => {
        const item = e.target.closest('article.service-card');
        if (!item) return;
        item.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
      });

      grid.addEventListener('dragover', (e) => {
        e.preventDefault();
        const afterElement = getDragAfterElement(grid, e.clientY);
        const dragging = grid.querySelector('.dragging');
        if (!dragging) return;
        if (afterElement == null) {
          grid.appendChild(dragging);
        } else {
          grid.insertBefore(dragging, afterElement);
        }
      });

      const persistOrder = async () => {
        const ids = getDraggables().map(el => el.getAttribute('data-id'));
        if (status) { status.style.display = 'block'; status.textContent = 'Guardando nuevo orden…'; }
        try {
          const res = await fetch('services.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reorder', csrf: csrf, order: ids })
          });
          const data = await res.json();
          if (!data.ok) throw new Error(data.error || 'Error desconocido');
          // Actualizar numeración visible
          getDraggables().forEach((el, idx) => {
            const num = el.querySelector('.js-order-num');
            if (num) num.textContent = (idx + 1);
          });
          if (status) { status.style.display = 'block'; status.style.color = 'green'; status.textContent = 'Orden guardado.'; }
          setTimeout(()=>{ if (status) status.style.display = 'none'; }, 1600);
        } catch (err) {
          if (status) { status.style.display = 'block'; status.style.color = '#b00020'; status.textContent = 'No se pudo guardar el orden: ' + err.message; }
        }
      };

      grid.addEventListener('dragend', () => {
        const dragging = grid.querySelector('.dragging');
        if (dragging) dragging.classList.remove('dragging');
        persistOrder();
      });
    })();
  </script>
</body>
</html>
<?php $mysqli->close(); ?>