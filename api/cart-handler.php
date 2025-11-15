<?php
require_once '../config/config.php';
require_once '../config/database.php';

ob_start();
header('Content-Type: application/json');

if (!isLoggedIn() || isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    ob_end_flush();
    exit();
}

$conn = getConnection();
$user_id = $_SESSION['user_id'] ?? 0;

// helper: detect whether cart PK column is 'cart_id' or 'id'
function getCartPrimaryKey($conn) {
    $db = null;
    $res = $conn->query("SELECT DATABASE() as db");
    if ($res) {
        $row = $res->fetch_assoc();
        $db = $row['db'] ?? null;
        $res->close();
    }
    $candidates = ['cart_id', 'id'];
    if (!$db) return 'cart_id';
    foreach ($candidates as $col) {
        $q = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'cart' AND COLUMN_NAME = ?";
        $s = $conn->prepare($q);
        if (!$s) continue;
        $s->bind_param("ss", $db, $col);
        $s->execute();
        $rr = $s->get_result()->fetch_assoc();
        $s->close();
        if (isset($rr['cnt']) && intval($rr['cnt']) > 0) return $col;
    }
    return 'cart_id';
}

$cart_pk = getCartPrimaryKey($conn); // e.g. 'cart_id' or 'id'
$cart_pk_escaped = str_replace('`','', $cart_pk); // safe identifier part

// read raw body for JSON requests
$rawBody = file_get_contents('php://input');
$decodedBody = null;
if ($rawBody) {
    $tmp = json_decode($rawBody, true);
    if (json_last_error() === JSON_ERROR_NONE) $decodedBody = $tmp;
}

// determine action
$action = '';
if (isset($_POST['action'])) $action = $_POST['action'];
elseif (is_array($decodedBody) && isset($decodedBody['action'])) $action = $decodedBody['action'];
elseif (isset($_GET['action'])) $action = $_GET['action'];

function getCartCount($conn, $user_id) {
    $query = "SELECT COUNT(*) as count FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $count = $res->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    return (int)$count;
}

function getVariasiStokForUpdate($conn, $variasi_id, $id_kostum) {
    $q = "SELECT stok FROM kostum_variasi WHERE id = ? AND id_kostum = ? LIMIT 1 FOR UPDATE";
    $stm = $conn->prepare($q);
    $stm->bind_param("ii", $variasi_id, $id_kostum);
    $stm->execute();
    $row = $stm->get_result()->fetch_assoc();
    $stm->close();
    return $row ? intval($row['stok']) : null;
}

function parseItems($rawBody, $decodedBody) {
    // form-encoded 'items' first
    if (isset($_POST['items'])) {
        $it = json_decode($_POST['items'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($it)) return $it;
    }
    if (is_array($decodedBody)) {
        if (isset($decodedBody['items']) && is_array($decodedBody['items'])) return $decodedBody['items'];
        if (isset($decodedBody[0]) && is_array($decodedBody)) return $decodedBody;
    }
    if ($rawBody) {
        $d = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($d['items']) && is_array($d['items'])) return $d['items'];
            if (isset($d[0]) && is_array($d)) return $d;
        }
    }
    return null;
}

switch ($action) {
    case 'add':
        $items = parseItems($rawBody, $decodedBody);

        if (is_array($items) && count($items) > 0) {
            // normalize / agregasi
            $kostum_items_map = [];
            $makeup_items = [];
            foreach ($items as $it) {
                $type = isset($it['type']) ? trim($it['type']) : '';
                if ($type === 'kostum') {
                    $pid = isset($it['product_id']) ? intval($it['product_id']) : 0;
                    $vid = isset($it['variasi_id']) ? intval($it['variasi_id']) : 0;
                    $qty = isset($it['quantity']) ? max(0,intval($it['quantity'])) : 0;
                    if ($pid > 0 && $vid > 0 && $qty > 0) {
                        $key = $pid . '_' . $vid;
                        if (!isset($kostum_items_map[$key])) $kostum_items_map[$key] = ['product_id'=>$pid,'variasi_id'=>$vid,'quantity'=>$qty];
                        else $kostum_items_map[$key]['quantity'] += $qty;
                    }
                } elseif ($type === 'makeup') {
                    $pid = isset($it['product_id']) ? intval($it['product_id']) : 0;
                    $jadwal = isset($it['jadwal_id']) ? intval($it['jadwal_id']) : 0;
                    if ($pid > 0 && $jadwal > 0) $makeup_items[] = ['product_id'=>$pid,'jadwal_id'=>$jadwal];
                }
            }

            $kostum_items = array_values($kostum_items_map);

            // transaksi
            $conn->begin_transaction();
            try {
                // validasi & lock stok variasi
                foreach ($kostum_items as $k => $kit) {
                    $qP = "SELECT id FROM kostum WHERE id = ? AND status = 'aktif' LIMIT 1";
                    $sP = $conn->prepare($qP);
                    $sP->bind_param("i", $kit['product_id']);
                    $sP->execute();
                    $p = $sP->get_result()->fetch_assoc();
                    $sP->close();
                    if (!$p) throw new Exception("Produk ID {$kit['product_id']} tidak ditemukan atau tidak aktif");

                    $stok_now = getVariasiStokForUpdate($conn, $kit['variasi_id'], $kit['product_id']);
                    if ($stok_now === null) throw new Exception("Variasi ID {$kit['variasi_id']} tidak ditemukan untuk produk {$kit['product_id']}");
                    if ($stok_now < $kit['quantity']) throw new Exception("Stok tidak mencukupi untuk variasi {$kit['variasi_id']}. Tersedia: $stok_now");
                    $kostum_items[$k]['stok_now'] = $stok_now;
                }

                // validasi jadwal makeup
                foreach ($makeup_items as $m) {
                    $qM = "SELECT id FROM layanan_makeup WHERE id = ? LIMIT 1";
                    $sM = $conn->prepare($qM);
                    $sM->bind_param("i", $m['product_id']);
                    $sM->execute();
                    $pm = $sM->get_result()->fetch_assoc();
                    $sM->close();
                    if (!$pm) throw new Exception("Layanan makeup ID {$m['product_id']} tidak ditemukan");

                    $qJ = "SELECT * FROM jadwal_makeup WHERE id = ? AND status = 'tersedia' LIMIT 1 FOR UPDATE";
                    $sJ = $conn->prepare($qJ);
                    $sJ->bind_param("i", $m['jadwal_id']);
                    $sJ->execute();
                    $jad = $sJ->get_result()->fetch_assoc();
                    $sJ->close();
                    if (!$jad) throw new Exception("Jadwal ID {$m['jadwal_id']} tidak tersedia");

                    // cek apakah user sudah memilih jadwal yang sama (avoid duplicate jadwal)
                    $qC = "SELECT * FROM cart WHERE user_id = ? AND type = 'makeup' AND jadwal_id = ? LIMIT 1";
                    $sC = $conn->prepare($qC);
                    $sC->bind_param("ii", $user_id, $m['jadwal_id']);
                    $sC->execute();
                    $ex = $sC->get_result()->fetch_assoc();
                    $sC->close();
                    if ($ex) throw new Exception("Anda sudah memilih jadwal ID {$m['jadwal_id']}");
                }

                // proses tiap kostum: cek row cart (lock), insert/update, kurangi stok
                foreach ($kostum_items as $kit) {
                    $pid = intval($kit['product_id']);
                    $vid = intval($kit['variasi_id']);
                    $qty = intval($kit['quantity']);

                    // dynamic select PK column as 'pk_val'
                    $sqlSelectCart = "SELECT `$cart_pk_escaped` AS pk_val, quantity FROM cart WHERE user_id = ? AND product_id = ? AND type = 'kostum' AND variasi_id = ? LIMIT 1 FOR UPDATE";
                    $s = $conn->prepare($sqlSelectCart);
                    if (!$s) throw new Exception("Gagal prepare select cart");
                    $s->bind_param("iii", $user_id, $pid, $vid);
                    $s->execute();
                    $existing = $s->get_result()->fetch_assoc();
                    $s->close();

                    if ($existing) {
                        $existing_pk = $existing['pk_val'];
                        $newQty = intval($existing['quantity']) + $qty;
                        $sqlUpdate = "UPDATE cart SET quantity = ? WHERE `$cart_pk_escaped` = ? AND user_id = ?";
                        $u = $conn->prepare($sqlUpdate);
                        if (!$u) throw new Exception("Gagal prepare update cart");
                        $u->bind_param("iii", $newQty, $existing_pk, $user_id);
                        if (!$u->execute()) throw new Exception("Gagal update cart: " . $u->error);
                        $u->close();
                    } else {
                        $ins = $conn->prepare("INSERT INTO cart (user_id, product_id, type, quantity, variasi_id, created_at) VALUES (?, ?, 'kostum', ?, ?, NOW())");
                        if (!$ins) throw new Exception("Gagal prepare insert cart");
                        $ins->bind_param("iiii", $user_id, $pid, $qty, $vid);
                        if (!$ins->execute()) throw new Exception("Gagal insert cart: " . $ins->error);
                        $ins->close();
                    }

                    $uStock = $conn->prepare("UPDATE kostum_variasi SET stok = stok - ? WHERE id = ? AND id_kostum = ?");
                    $uStock->bind_param("iii", $qty, $vid, $pid);
                    $uStock->execute();
                    if ($uStock->affected_rows === 0) throw new Exception("Gagal mengurangi stok untuk variasi {$vid}");
                    $uStock->close();
                }

                // proses makeup
                foreach ($makeup_items as $mit) {
                    // Insert makeup row: set BOTH jadwal_id and variasi_id=jadwal_id
                    // so unique key (user_id, product_id, type, variasi_id) will be unique per jadwal
                    $ins = $conn->prepare("INSERT INTO cart (user_id, product_id, type, quantity, jadwal_id, variasi_id, created_at) VALUES (?, ?, 'makeup', 1, ?, ?, NOW())");
                    if (!$ins) throw new Exception("Gagal prepare insert cart (makeup)");
                    $ins->bind_param("iiii", $user_id, $mit['product_id'], $mit['jadwal_id'], $mit['jadwal_id']);
                    if (!$ins->execute()) throw new Exception("Gagal menambah cart untuk jadwal {$mit['jadwal_id']}: " . $ins->error);
                    $ins->close();

                    $uJ = $conn->prepare("UPDATE jadwal_makeup SET status = 'dipesan' WHERE id = ?");
                    $uJ->bind_param("i", $mit['jadwal_id']);
                    if (!$uJ->execute()) throw new Exception("Gagal update jadwal_makeup: " . $uJ->error);
                    $uJ->close();
                }

                $conn->commit();
                echo json_encode(['success'=>true,'message'=>'Semua item berhasil ditambahkan ke keranjang','cart_count'=>getCartCount($conn,$user_id)]);
                ob_end_flush();
                exit();

            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success'=>false,'message'=>'Gagal menambahkan item: '.$e->getMessage(),'cart_count'=>getCartCount($conn,$user_id)]);
                ob_end_flush();
                exit();
            }
        }

        // legacy single-add fallback (form-encoded or single JSON item)
        $type = isset($_POST['type']) ? trim($_POST['type']) : (is_array($decodedBody) && isset($decodedBody['type']) ? $decodedBody['type'] : '');
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : (is_array($decodedBody) && isset($decodedBody['product_id']) ? intval($decodedBody['product_id']) : 0);
        $quantity = isset($_POST['quantity']) ? max(1,intval($_POST['quantity'])) : (is_array($decodedBody) && isset($decodedBody['quantity']) ? max(1,intval($decodedBody['quantity'])) : 1);
        $jadwal_id = isset($_POST['jadwal_id']) ? intval($_POST['jadwal_id']) : (is_array($decodedBody) && isset($decodedBody['jadwal_id']) ? intval($decodedBody['jadwal_id']) : null);
        $variasi_id = isset($_POST['variasi_id']) ? intval($_POST['variasi_id']) : (is_array($decodedBody) && isset($decodedBody['variasi_id']) ? intval($decodedBody['variasi_id']) : null);

        if (!in_array($type, ['kostum','makeup'])) {
            echo json_encode(['success'=>false,'message'=>'Tipe produk tidak valid','cart_count'=>getCartCount($conn,$user_id)]);
            ob_end_flush();
            exit();
        }

        if ($type === 'kostum') {
            if (!$variasi_id) { echo json_encode(['success'=>false,'message'=>'Pilih ukuran kostum terlebih dahulu','cart_count'=>getCartCount($conn,$user_id)]); ob_end_flush(); exit(); }
            $conn->begin_transaction();
            try {
                $stok_now = getVariasiStokForUpdate($conn, $variasi_id, $product_id);
                if ($stok_now === null) throw new Exception('Variasi kostum tidak ditemukan');
                if ($stok_now < $quantity) throw new Exception('Stok tidak mencukupi. Stok tersedia: ' . $stok_now);

                // check existing cart row (lock)
                $sqlSelectCart = "SELECT `$cart_pk_escaped` AS pk_val, quantity FROM cart WHERE user_id = ? AND product_id = ? AND type = 'kostum' AND variasi_id = ? LIMIT 1 FOR UPDATE";
                $s = $conn->prepare($sqlSelectCart);
                $s->bind_param("iii", $user_id, $product_id, $variasi_id);
                $s->execute();
                $existing = $s->get_result()->fetch_assoc();
                $s->close();

                if ($existing) {
                    $existing_pk = $existing['pk_val'];
                    $newQty = intval($existing['quantity']) + $quantity;
                    $sqlUpdate = "UPDATE cart SET quantity = ? WHERE `$cart_pk_escaped` = ? AND user_id = ?";
                    $u = $conn->prepare($sqlUpdate);
                    $u->bind_param("iii", $newQty, $existing_pk, $user_id);
                    if (!$u->execute()) throw new Exception('Gagal update cart: ' . $u->error);
                    $u->close();
                } else {
                    $ins = $conn->prepare("INSERT INTO cart (user_id, product_id, type, quantity, variasi_id, created_at) VALUES (?, ?, 'kostum', ?, ?, NOW())");
                    $ins->bind_param("iiii", $user_id, $product_id, $quantity, $variasi_id);
                    if (!$ins->execute()) throw new Exception('Gagal insert cart: ' . $ins->error);
                    $ins->close();
                }

                $u = $conn->prepare("UPDATE kostum_variasi SET stok = stok - ? WHERE id = ? AND id_kostum = ?");
                $u->bind_param("iii", $quantity, $variasi_id, $product_id);
                $u->execute();
                if ($u->affected_rows === 0) throw new Exception('Gagal mengurangi stok pada variasi');
                $u->close();

                $conn->commit();
                echo json_encode(['success'=>true,'message'=>'Kostum berhasil ditambahkan ke keranjang','cart_count'=>getCartCount($conn,$user_id)]);
                ob_end_flush();
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success'=>false,'message'=>'Gagal menambahkan kostum: '.$e->getMessage(),'cart_count'=>getCartCount($conn,$user_id)]);
                ob_end_flush();
                exit();
            }
        } else {
            // legacy makeup
            if (!$jadwal_id) { echo json_encode(['success'=>false,'message'=>'Pilih jadwal terlebih dahulu','cart_count'=>getCartCount($conn,$user_id)]); ob_end_flush(); exit(); }
            $qJ = "SELECT * FROM jadwal_makeup WHERE id = ? AND status = 'tersedia' LIMIT 1";
            $sJ = $conn->prepare($qJ);
            $sJ->bind_param("i", $jadwal_id);
            $sJ->execute();
            $jadwal = $sJ->get_result()->fetch_assoc();
            $sJ->close();
            if (!$jadwal) { echo json_encode(['success'=>false,'message'=>'Jadwal tidak tersedia','cart_count'=>getCartCount($conn,$user_id)]); ob_end_flush(); exit(); }

            // check if already selected same jadwal (prevent duplicate)
            $qC = "SELECT * FROM cart WHERE user_id = ? AND type = 'makeup' AND jadwal_id = ? LIMIT 1";
            $sC = $conn->prepare($qC);
            $sC->bind_param("ii", $user_id, $jadwal_id);
            $sC->execute();
            $existJ = $sC->get_result()->fetch_assoc();
            $sC->close();
            if ($existJ) { echo json_encode(['success'=>false,'message'=>'Jadwal ini sudah Anda pilih','cart_count'=>getCartCount($conn,$user_id)]); ob_end_flush(); exit(); }

            // Insert with both jadwal_id and variasi_id = jadwal_id to avoid unique key collisions
            $ins = $conn->prepare("INSERT INTO cart (user_id, product_id, type, quantity, jadwal_id, variasi_id, created_at) VALUES (?, ?, 'makeup', 1, ?, ?, NOW())");
            $ins->bind_param("iiii", $user_id, $product_id, $jadwal_id, $jadwal_id);
            if (!$ins->execute()) {
                echo json_encode(['success'=>false,'message'=>'Gagal menambah makeup ke keranjang: '.$ins->error,'cart_count'=>getCartCount($conn,$user_id)]);
                ob_end_flush();
                exit();
            }
            $ins->close();

            $u = $conn->prepare("UPDATE jadwal_makeup SET status = 'dipesan' WHERE id = ?");
            $u->bind_param("i", $jadwal_id);
            $u->execute();
            $u->close();

            echo json_encode(['success'=>true,'message'=>'Makeup berhasil ditambahkan ke keranjang','cart_count'=>getCartCount($conn,$user_id)]);
            ob_end_flush();
            exit();
        }
        break;

    case 'delete':
        $cart_id = intval($_POST['cart_id'] ?? 0);
        if ($cart_id <= 0) { echo json_encode(['success'=>false,'message'=>'cart_id tidak valid','cart_count'=>getCartCount($conn,$user_id)]); ob_end_flush(); exit(); }

        // try both possible PK names cart_id or id
        $stmt = $conn->prepare("SELECT * FROM cart WHERE cart_id = ? AND user_id = ? LIMIT 1");
        $item = null;
        if ($stmt) {
            $stmt->bind_param("ii", $cart_id, $user_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        if (!$item) {
            $stmt2 = $conn->prepare("SELECT * FROM cart WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt2->bind_param("ii", $cart_id, $user_id);
            $stmt2->execute();
            $item = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        }
        if (!$item) { echo json_encode(['success'=>false,'message'=>'Item tidak ditemukan','cart_count'=>getCartCount($conn,$user_id)]); ob_end_flush(); exit(); }

        // restore stok/jadwal jika perlu
        if ($item['type'] === 'makeup' && !empty($item['jadwal_id'])) {
            $u = $conn->prepare("UPDATE jadwal_makeup SET status = 'tersedia' WHERE id = ?");
            $u->bind_param("i", $item['jadwal_id']);
            $u->execute();
            $u->close();
        }
        if ($item['type'] === 'kostum' && !empty($item['variasi_id'])) {
            $restore_qty = intval($item['quantity']);
            if ($restore_qty > 0) {
                $u = $conn->prepare("UPDATE kostum_variasi SET stok = stok + ? WHERE id = ? AND id_kostum = ?");
                $u->bind_param("iii", $restore_qty, $item['variasi_id'], $item['product_id']);
                $u->execute();
                $u->close();
            }
        }

        // delete row
        $del = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
        $del->bind_param("ii", $cart_id, $user_id);
        $del->execute();
        if ($del->affected_rows === 0) {
            $del->close();
            $del2 = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $del2->bind_param("ii", $cart_id, $user_id);
            $del2->execute();
            $del2->close();
        } else {
            $del->close();
        }

        echo json_encode(['success'=>true,'message'=>'Item berhasil dihapus dari keranjang','cart_count'=>getCartCount($conn,$user_id)]);
        ob_end_flush();
        exit();
        break;

    case 'update':
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        if ($cart_id <= 0) { echo json_encode(['success'=>false,'message'=>'cart_id tidak valid','cart_count'=>getCartCount($conn,$user_id)]); ob_end_flush(); exit(); }

        // fetch existing row (try both PK names)
        $cart_item = null;
        $s1 = $conn->prepare("SELECT * FROM cart WHERE id = ? AND user_id = ? LIMIT 1");
        if ($s1) {
            $s1->bind_param("ii", $cart_id, $user_id);
            $s1->execute();
            $cart_item = $s1->get_result()->fetch_assoc();
            $s1->close();
        }
        if (!$cart_item) {
            $s2 = $conn->prepare("SELECT * FROM cart WHERE cart_id = ? AND user_id = ? LIMIT 1");
            $s2->bind_param("ii", $cart_id, $user_id);
            $s2->execute();
            $cart_item = $s2->get_result()->fetch_assoc();
            $s2->close();
        }
        if (!$cart_item) { echo json_encode(['success'=>false,'message'=>'Item tidak ditemukan','cart_count'=>getCartCount($conn,$user_id)]); ob_end_flush(); exit(); }
        if ($cart_item['type'] !== 'kostum') { echo json_encode(['success'=>false,'message'=>'Hanya jumlah kostum yang bisa diubah','cart_count'=>getCartCount($conn,$user_id)]); ob_end_flush(); exit(); }

        $conn->begin_transaction();
        try {
            $old_qty = intval($cart_item['quantity']);
            $diff = $quantity - $old_qty;
            if (!empty($cart_item['variasi_id'])) {
                $vid = intval($cart_item['variasi_id']);
                $pid = intval($cart_item['product_id']);
                if ($diff > 0) {
                    $stok_now = getVariasiStokForUpdate($conn, $vid, $pid);
                    if ($stok_now === null || $stok_now < $diff) throw new Exception('Stok tidak mencukupi untuk menambah jumlah. Stok tersedia: ' . ($stok_now ?? 0));
                    $u = $conn->prepare("UPDATE kostum_variasi SET stok = stok - ? WHERE id = ? AND id_kostum = ?");
                    $u->bind_param("iii", $diff, $vid, $pid);
                    $u->execute();
                    if ($u->affected_rows === 0) throw new Exception('Gagal mengurangi stok');
                    $u->close();
                } elseif ($diff < 0) {
                    $restore = abs($diff);
                    $u = $conn->prepare("UPDATE kostum_variasi SET stok = stok + ? WHERE id = ? AND id_kostum = ?");
                    $u->bind_param("iii", $restore, $vid, $pid);
                    $u->execute();
                    $u->close();
                }
            }

            // update cart quantity - try both PK names
            $q = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ? AND type = 'kostum'");
            $q->bind_param("iii", $quantity, $cart_id, $user_id);
            $q->execute();
            if ($q->affected_rows === 0) {
                $q->close();
                $q2 = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND user_id = ? AND type = 'kostum'");
                $q2->bind_param("iii", $quantity, $cart_id, $user_id);
                $q2->execute();
                $q2->close();
            } else {
                $q->close();
            }

            $conn->commit();
            echo json_encode(['success'=>true,'message'=>'Jumlah kostum diperbarui','cart_count'=>getCartCount($conn,$user_id)]);
            ob_end_flush();
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success'=>false,'message'=>'Gagal memperbarui jumlah: '.$e->getMessage(),'cart_count'=>getCartCount($conn,$user_id)]);
            ob_end_flush();
            exit();
        }
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Aksi tidak valid','cart_count'=>getCartCount($conn,$user_id)]);
        ob_end_flush();
        exit();
        break;
}

ob_end_flush();
