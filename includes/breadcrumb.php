<?php
// includes/breadcrumb.php
// Renders a breadcrumb navigation based on the $breadcrumbs array.
// $breadcrumbs should be an array of arrays, e.g.,
// [
//   ['label' => 'Home', 'link' => 'dashboard.php'],
//   ['label' => 'Member Management', 'link' => 'member_management.php'],
//   ['label' => 'Edit Member', 'link' => ''] // Current page has no link
// ]

if (!empty($breadcrumbs) && is_array($breadcrumbs)): ?>
    <nav class="breadcrumb-nav" aria-label="breadcrumb">
        <ol class="breadcrumb">
            <?php foreach ($breadcrumbs as $index => $crumb):
                $is_last = ($index === count($breadcrumbs) - 1); ?>
                <li class="breadcrumb-item<?php echo $is_last ? ' active' : ''; ?>" <?php echo $is_last ? 'aria-current="page"' : ''; ?>>
                    <?php if ($crumb['link'] && !$is_last): ?>
                        <a href="<?php echo htmlspecialchars($crumb['link']); ?>"><?php echo htmlspecialchars($crumb['label']); ?></a>
                    <?php else: ?>
                        <?php echo htmlspecialchars($crumb['label']); ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
<?php endif; ?>