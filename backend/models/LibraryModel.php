<?php
// backend/models/LibraryModel.php
class LibraryModel {

    /* -------- Categories -------- */
    public static function getCategories(PDO $pdo): array {
        $stmt = $pdo->query("SELECT id, name, year FROM library_categories ORDER BY name, year DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function saveCategory(PDO $pdo, string $name, $year = null): bool {
        $sql = "INSERT INTO library_categories (name, year, created_at) VALUES (?, ?, NOW())";
        $st = $pdo->prepare($sql);
        return $st->execute([$name, $year]);
    }

    public static function deleteCategory(PDO $pdo, int $id): bool {
        // Ensure no books reference this
        $st = $pdo->prepare("SELECT COUNT(*) FROM library_books WHERE category_id = ?");
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) return false;
        $st = $pdo->prepare("DELETE FROM library_categories WHERE id = ?");
        return $st->execute([$id]);
    }

    /* -------- Books -------- */
    public static function getBooks(PDO $pdo): array {
        $sql = "SELECT b.*, c.name AS category_name
                FROM library_books b
                LEFT JOIN library_categories c ON c.id = b.category_id
                ORDER BY b.title ASC";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getBook(PDO $pdo, int $id) {
        $st = $pdo->prepare("SELECT * FROM library_books WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Save book (insert or update). Keeps available_qty consistent with total_qty
     * $data keys: id(optional), title, author, isbn, category_id, rack_no, total_qty,
     * publisher, edition, published_year, price, language, acquisition_date, vendor, file_path, file_name
     */
    public static function saveBook(PDO $pdo, array $data, ?int $uploaderId = null): bool {
        if (!empty($data['id'])) {
            // update
            $book = self::getBook($pdo, (int)$data['id']);
            if (!$book) return false;
            $oldTotal = (int)$book['total_qty'];
            $oldAvail = (int)$book['available_qty'];

            $newTotal = max(1, (int)$data['total_qty']);
            $delta = $newTotal - $oldTotal;
            $newAvail = max(0, $oldAvail + $delta);

            $sql = "UPDATE library_books SET
                        title=:title, author=:author, isbn=:isbn, category_id=:category_id,
                        rack_no=:rack_no, total_qty=:total_qty, available_qty=:available_qty,
                        publisher=:publisher, edition=:edition, published_year=:published_year,
                        price=:price, language=:language, acquisition_date=:acquisition_date,
                        vendor=:vendor
                    WHERE id=:id";
            $st = $pdo->prepare($sql);
            return $st->execute([
                ':title'=>$data['title'], ':author'=>$data['author'], ':isbn'=>$data['isbn'],
                ':category_id'=>$data['category_id'] ?? null, ':rack_no'=>$data['rack_no'] ?? null,
                ':total_qty'=>$newTotal, ':available_qty'=>$newAvail,
                ':publisher'=>$data['publisher'] ?? null, ':edition'=>$data['edition'] ?? null,
                ':published_year'=>$data['published_year'] ?? null, ':price'=>$data['price'] ?? null,
                ':language'=>$data['language'] ?? null, ':acquisition_date'=>$data['acquisition_date'] ?? null,
                ':vendor'=>$data['vendor'] ?? null,
                ':id'=>$data['id']
            ]);
        } else {
            // insert
            $sql = "INSERT INTO library_books
                (title,author,isbn,category_id,rack_no,total_qty,available_qty,
                 publisher,edition,published_year,price,language,acquisition_date,vendor,file_path,file_name,uploaded_by,created_at)
                VALUES (:title,:author,:isbn,:category_id,:rack_no,:total_qty,:available_qty,
                        :publisher,:edition,:published_year,:price,:language,:acquisition_date,:vendor,:file_path,:file_name,:uploaded_by,NOW())";
            $st = $pdo->prepare($sql);
            $total = max(1, (int)($data['total_qty'] ?? 1));
            return $st->execute([
                ':title'=>$data['title'], ':author'=>$data['author'], ':isbn'=>$data['isbn'],
                ':category_id'=>$data['category_id'] ?? null, ':rack_no'=>$data['rack_no'] ?? null,
                ':total_qty'=>$total, ':available_qty'=>$total,
                ':publisher'=>$data['publisher'] ?? null, ':edition'=>$data['edition'] ?? null,
                ':published_year'=>$data['published_year'] ?? null, ':price'=>$data['price'] ?? null,
                ':language'=>$data['language'] ?? null, ':acquisition_date'=>$data['acquisition_date'] ?? null,
                ':vendor'=>$data['vendor'] ?? null,
                ':file_path'=>$data['file_path'] ?? null, ':file_name'=>$data['file_name'] ?? null,
                ':uploaded_by'=>$uploaderId
            ]);
        }
    }

    public static function deleteBook(PDO $pdo, int $id): bool {
        // Will cascade loans if FK exists; safe delete
        $st = $pdo->prepare("DELETE FROM library_books WHERE id = ?");
        return $st->execute([$id]);
    }

    /* -------- Loans / Transactions -------- */

    public static function issueBook(PDO $pdo, int $book_id, int $borrower_id, int $issuer_id, string $issue_date, string $due_date): bool {
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT available_qty FROM library_books WHERE id = ? FOR UPDATE");
            $st->execute([$book_id]);
            $avail = (int)$st->fetchColumn();
            if ($avail < 1) { $pdo->rollBack(); return false; }

            $ins = $pdo->prepare("INSERT INTO library_loans (book_id, borrower_id, issued_by, issue_date, due_date, status, created_at)
                                  VALUES (?, ?, ?, ?, ?, 'issued', NOW())");
            $ins->execute([$book_id, $borrower_id, $issuer_id, $issue_date, $due_date]);

            $upd = $pdo->prepare("UPDATE library_books SET available_qty = available_qty - 1 WHERE id = ?");
            $upd->execute([$book_id]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public static function returnBook(PDO $pdo, int $loan_id, string $return_date): bool {
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT l.*, b.id AS book_id FROM library_loans l LEFT JOIN library_books b ON b.id = l.book_id WHERE l.id = ? FOR UPDATE");
            $st->execute([$loan_id]);
            $loan = $st->fetch(PDO::FETCH_ASSOC);
            if (!$loan) { $pdo->rollBack(); return false; }
            if (!in_array($loan['status'], ['issued','overdue'], true)) { $pdo->rollBack(); return false; }

            // calculate late days (for note or possible fine)
            $lateDays = 0;
            if (!empty($loan['due_date'])) {
                $due = new DateTime($loan['due_date']);
                $ret = new DateTime($return_date);
                if ($ret > $due) $lateDays = (int)$due->diff($ret)->days;
            }

            $remarks = $loan['remarks'] ?? '';
            if ($lateDays > 0) {
                $notes = "Returned {$lateDays} day(s) late";
                $remarks = $remarks ? ($remarks . ' | ' . $notes) : $notes;
            }

            $upd = $pdo->prepare("UPDATE library_loans SET return_date = ?, status = 'returned', remarks = ? WHERE id = ?");
            $upd->execute([$return_date, $remarks, $loan_id]);

            $pdo->prepare("UPDATE library_books SET available_qty = available_qty + 1 WHERE id = ?")
                ->execute([$loan['book_id']]);

            // Optionally create a fine record (not auto-calculated here)
            if ($lateDays > 0) {
                $stmt = $pdo->prepare("INSERT INTO library_fines (loan_id, amount, reason, paid, created_at) VALUES (?, ?, ?, 0, NOW())");
                // default amount - you may change business rule
                $stmt->execute([$loan_id, $lateDays * 1.00, "Late return ({$lateDays} days)"]);
            }

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public static function getOpenLoans(PDO $pdo): array {
        $sql = "SELECT l.*, b.title, b.isbn, c.name AS borrower_name
                FROM library_loans l
                LEFT JOIN library_books b ON b.id = l.book_id
                LEFT JOIN courts c ON c.id = l.borrower_id
                WHERE l.status IN ('issued','overdue')
                ORDER BY l.issue_date DESC";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getTransactions(PDO $pdo, $limit = 200): array {
        $sql = "SELECT l.*, b.title AS book_title, c.name AS borrower_name
                FROM library_loans l
                LEFT JOIN library_books b ON b.id = l.book_id
                LEFT JOIN courts c ON c.id = l.borrower_id
                ORDER BY l.created_at DESC
                LIMIT " . (int)$limit;
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /* -------- Members (external) -------- */
    public static function addMember(PDO $pdo, array $data): bool {
        $st = $pdo->prepare("INSERT INTO library_members (name, card_no, email, contact, address, member_type, valid_from, valid_until, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        return $st->execute([
            $data['name'], $data['card_no'] ?? null, $data['email'] ?? null, $data['contact'] ?? null,
            $data['address'] ?? null, $data['member_type'] ?? 'external',
            $data['valid_from'] ?? null, $data['valid_until'] ?? null
        ]);
    }

    public static function getMembers(PDO $pdo): array {
        $st = $pdo->query("SELECT id, name, card_no, email, contact, member_type, valid_from, valid_until FROM library_members ORDER BY name");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /* -------- Courts as borrowers -------- */
    public static function getCourts(PDO $pdo): array {
        $st = $pdo->query("SELECT id, name FROM courts ORDER BY name");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}