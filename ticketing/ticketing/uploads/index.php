<?php
// Mencegah directory listing
http_response_code(403);
exit('Akses langsung ditolak.');
