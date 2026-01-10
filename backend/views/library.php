<?php
// backend/views/library.php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user']['role'] ?? null;
$role_header_path = __DIR__ . '/header_' . ($role ?: 'guest') . '.php';
$default_header_path = __DIR__ . '/header.php';
if (file_exists($role_header_path)) include $role_header_path; else include $default_header_path;

if (!in_array($_SESSION['user']['role'] ?? '', ['admin','librarian'], true)) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 m-4">Access denied</div>';
    include __DIR__ . '/footer.php';
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        @media (max-width: 640px) {
            .tab-buttons { flex-direction: column; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-6 max-w-7xl">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Library Management</h2>
        <?php if ($flash): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <?= htmlspecialchars($flash) ?>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="flex tab-buttons border-b border-gray-200 mb-6">
            <button class="tab-button px-4 py-2 text-gray-600 font-medium border-b-2 border-transparent hover:border-blue-500 focus:outline-none active" data-tab="add-edit-book">Add/Edit Book</button>
            <button class="tab-button px-4 py-2 text-gray-600 font-medium border-b-2 border-transparent hover:border-blue-500 focus:outline-none" data-tab="issue-return">Issue/Return</button>
            <button class="tab-button px-4 py-2 text-gray-600 font-medium border-b-2 border-transparent hover:border-blue-500 focus:outline-none" data-tab="books-catalog">Books Catalog</button>
        </div>

        <!-- Tab Content -->
        <div class="tab-content active" id="add-edit-book">
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Add / Edit Book</h3>
                <form action="?page=save_book" method="post" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="id" value="">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="row">
                        <label for="">Title</label>
                        <input type="text" name="title" placeholder="Title" required class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                        <label for="">Author</label>
                        <input type="text" name="author" placeholder="Author" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <input type="text" name="isbn" placeholder="ISBN" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                        <select name="category_id" required class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Category --</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?><?= $c['year'] ? " ({$c['year']})" : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="rack_no" placeholder="Rack/Call no" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <input type="number" name="total_qty" min="1" value="1" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                        <input type="text" name="publisher" placeholder="Publisher" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                        <input type="text" name="edition" placeholder="Edition" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                        <input type="number" name="published_year" placeholder="Year" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <input type="number" step="0.01" name="price" placeholder="Price" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                        <input type="text" name="language" placeholder="Language" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                        <input type="date" name="acquisition_date" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                        <input type="text" name="vendor" placeholder="Vendor" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex items-center gap-4">
                        <input type="file" name="book_file" class="p-2">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Save Book</button>
                    </div>
                </form>

                <hr class="my-6">
                <h4 class="text-md font-semibold text-gray-700 mb-4">Categories</h4>
                <form action="?page=save_library_category" method="post" class="flex gap-4 mb-4">
                    <input type="text" name="name" placeholder="New category" required class="flex-1 p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                    <input type="number" name="year" placeholder="Year" class="w-24 p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Add</button>
                </form>
                <ul class="list-disc pl-5">
                    <?php if (!empty($categories)): foreach ($categories as $cat): ?>
                        <li class="flex justify-between items-center">
                            <span><?= htmlspecialchars($cat['name']) ?> <?= $cat['year'] ? "({$cat['year']})" : '' ?></span>
                            <a href="?page=delete_library_category&id=<?= (int)$cat['id'] ?>" onclick="return confirm('Delete?');" class="text-red-600 hover:underline">Delete</a>
                        </li>
                    <?php endforeach; else: ?>
                        <li>No categories</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="tab-content" id="issue-return">
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Issue / Return</h3>
                <form action="?page=issue_book" method="post" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Category</label>
                            <select id="categoryFilter" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                                <option value="">-- All Categories --</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?><?= $c['year'] ? " ({$c['year']})" : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Published Year</label>
                            <input id="yearFilter" type="number" placeholder="Enter Year" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Book</label>
                        <select name="book_id" id="bookSelect" required class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Select Book --</option>
                            <?php foreach ($books as $b): if ((int)$b['available_qty'] > 0): ?>
                                <option value="<?= (int)$b['id'] ?>" data-category="<?= (int)$b['category_id'] ?>" data-year="<?= htmlspecialchars($b['published_year'] ?? '') ?>">
                                    <?= htmlspecialchars($b['title']) ?> (Avail: <?= (int)$b['available_qty'] ?>)
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Borrower (Court)</label>
                        <select name="borrower_id" required class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Select Court --</option>
                            <?php
                                $courts = $courts ?? (isset($pdo) ? $pdo->query("SELECT id, name FROM courts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) : []);
                                foreach ($courts as $court): ?>
                                <option value="<?= (int)$court['id'] ?>">
                                    <?= htmlspecialchars($court['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Issue Date</label>
                            <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Due Date</label>
                            <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Issue</button>
                        </div>
                    </div>
                </form>

                <hr class="my-6">
                <h4 class="text-md font-semibold text-gray-700 mb-4">Open Loans</h4>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="p-2 text-left text-sm font-medium text-gray-600">#</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Title</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Borrower (Court)</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Issue</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Due</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($loans)): foreach ($loans as $i => $l):
                                $isOverdue = false;
                                if (!empty($l['due_date'])) {
                                    $today = new DateTime(date('Y-m-d'));
                                    $due = new DateTime($l['due_date']);
                                    if ($today > $due) $isOverdue = true;
                                }
                            ?>
                                <tr class="<?= $isOverdue ? 'bg-red-50' : '' ?>">
                                    <td class="p-2 text-sm text-gray-600"><?= $i+1 ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= htmlspecialchars($l['title']) ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= htmlspecialchars($l['borrower_name'] ?? 'Unknown') ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= htmlspecialchars($l['issue_date']) ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= htmlspecialchars($l['due_date']) ?><?= $isOverdue ? ' <small class="text-red-600">(overdue)</small>' : '' ?></td>
                                    <td class="p-2">
                                        <form action="?page=return_book" method="post" class="inline">
                                            <input type="hidden" name="loan_id" value="<?= (int)$l['id'] ?>">
                                            <button type="submit" onclick="return confirm('Mark returned?')" class="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-blue-700 text-sm">Return</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" class="p-2 text-sm text-gray-600 text-center">No open loans</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-content" id="books-catalog">
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Books Catalog</h3>
                <input id="bookSearch" type="text" placeholder="Search title/author/ISBN..." class="w-full p-2 border rounded-md mb-4 focus:ring-2 focus:ring-blue-500">
                <div class="overflow-x-auto">
                    <table id="booksTable" class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="p-2 text-left text-sm font-medium text-gray-600">#</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Title</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Author</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">ISBN</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Category</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Publisher</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Year</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Price</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Total</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Available</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">File</th>
                                <th class="p-2 text-left text-sm font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($books)): foreach ($books as $i => $b): ?>
                                <tr data-title="<?= htmlspecialchars(strtolower($b['title'])) ?>" data-author="<?= htmlspecialchars(strtolower($b['author'])) ?>" data-isbn="<?= htmlspecialchars(strtolower($b['isbn'])) ?>">
                                    <td class="p-2 text-sm text-gray-600"><?= $i+1 ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= htmlspecialchars($b['title']) ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= htmlspecialchars($b['author']) ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= htmlspecialchars($b['isbn']) ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= htmlspecialchars($b['category_name']) ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= htmlspecialchars($b['publisher'] ?? '') ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= htmlspecialchars($b['published_year'] ?? '') ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= htmlspecialchars($b['price'] ?? '') ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= (int)$b['total_qty'] ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= (int)$b['available_qty'] ?></td>
                                    <td class="p-2 text-sm text-gray-600"><?= !empty($b['file_path']) ? '<a href="?page=library_download&id=' . (int)$b['id'] . '" class="text-blue-600 hover:underline">Download</a>' : '' ?></td>
                                    <td class="p-2 text-sm text-gray-600">
                                        <a href="?page=delete_book&id=<?= (int)$b['id'] ?>" onclick="return confirm('Delete?')" class="text-red-600 hover:underline">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="12" class="p-2 text-sm text-gray-600 text-center">No books</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
document.querySelectorAll('.tab-button').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
        this.classList.add('active');
        document.getElementById(this.dataset.tab).classList.add('active');
    });
});
document.getElementById('bookSearch').addEventListener('input', function() {
    let q = this.value.trim().toLowerCase();
    document.querySelectorAll('#booksTable tbody tr').forEach(tr => {
        let t = tr.dataset.title, a = tr.dataset.author, i = tr.dataset.isbn;
        tr.style.display = (t.includes(q) || a.includes(q) || i.includes(q)) ? '' : 'none';
    });
});
document.getElementById('categoryFilter').addEventListener('change', filterBooks);
document.getElementById('yearFilter').addEventListener('input', filterBooks);
function filterBooks() {
    let cat = document.getElementById('categoryFilter').value;
    let year = document.getElementById('yearFilter').value;
    document.querySelectorAll('#bookSelect option').forEach(opt => {
        if (!opt.value) return;
        let c = opt.dataset.category;
        let y = opt.dataset.year;
        opt.style.display = (!cat || c === cat) && (!year || y === year) ? '' : 'none';
    });
}
</script>
</body>
</html>
<?php include __DIR__ . '/footer.php'; ?>