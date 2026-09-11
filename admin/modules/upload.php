<?php
// Upload ảnh cho trình soạn thảo (Quill). Trả JSON {url}.
header('Content-Type: application/json; charset=utf-8');
if (!is_post()) { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
try {
    $path = handle_upload($_FILES['file'] ?? null, 'editor');
    if (!$path) throw new UserError('Chưa chọn ảnh.');
    echo json_encode(['url' => media($path)]);
} catch (UserError $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
