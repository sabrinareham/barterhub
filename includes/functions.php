<?php
// functions.php - small helpers we reuse everywhere

// Make text safe before printing it into HTML (stops <script> injection)
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Send the browser to another page and stop running this one
function redirect($path) {
    header('Location: ' . $path);
    exit;
}

// Flash message = a note that is shown ONCE on the next page, then forgotten
function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);   // show only once
    return $flash;
}

// NEW in Lesson 4: pick a Bootstrap icon for a category name.
// Names we know get their own icon; any new category the admin adds gets 'tag'.
function category_icon($name) {
    $icons = [
       
        'Books by sabrina' => 'book-half',
        'Electronics'      => 'laptop',
        'Furniture'        => 'lamp',
        'Clothing'         => 'handbag',
        'Sports'           => 'trophy',
        'Kitchen'          => 'cup-hot',
        'Toys'             => 'controller',
        'Mobiles'          => 'phone',
        'watch'            => 'smartwatch',
        'Make-up'          => 'magic', // Make-up ka icon yahan set ho gaya
    ];
    return $icons[$name] ?? 'tag';
}

// NEW in Lesson 4B: turn "Garden Tools & More" into "garden-tools-more"
function make_slug($text) {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);   // anything that is not a-z or 0-9 becomes "-"
    $slug = trim($slug, '-');                           // no "-" at the start or end
    return $slug !== '' ? $slug : 'category';           // names with no latin letters get a safe default
}

// NEW in Lesson 4B: make sure no other category already uses this slug.
// If "books" is taken we try "books-2", then "books-3", ...
function unique_slug($pdo, $base, $ignoreId = 0) {
    $slug = $base;
    $n = 2;
    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ? AND id <> ?');
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $n;
        $n++;
    }
}