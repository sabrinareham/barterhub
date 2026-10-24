<?php
// includes/item_card.php - one item card.
// The page sets $item first (id, title, wants, photo, item_condition, city, category_name).
?>
<div class="card h-100 position-relative">
  <?php if ($item['photo']): ?>
    <img src="<?= BASE_URL ?>/uploads/items/<?= e($item['photo']) ?>" class="card-img-top" style="height:180px;object-fit:cover" alt="">
  <?php else: ?>
    <div class="bg-light d-flex align-items-center justify-content-center" style="height:180px"><i class="bi bi-image fs-1 text-muted"></i></div>
  <?php endif; ?>
  <div class="card-body">
    <span class="badge text-bg-light border"><?= e($item['category_name']) ?></span>
    <h6 class="mt-2 mb-1">
      <a class="stretched-link text-decoration-none text-dark" href="<?= BASE_URL ?>/item.php?id=<?= (int)$item['id'] ?>"><?= e($item['title']) ?></a>
    </h6>
    <p class="small mb-1"><strong>Wants:</strong> <?= e($item['wants']) ?></p>
    <p class="small text-muted mb-0"><i class="bi bi-geo-alt"></i> <?= e($item['city']) ?> · <?= e(str_replace('_', ' ', $item['item_condition'])) ?></p>
  </div>
</div>