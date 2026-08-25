<?php

declare(strict_types=1);

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="600">
    <title>Monitoramento de NVRs</title>
    <link rel="stylesheet" href="assets/css/monitoring.css">
</head>
<body>
    <main>
        <header>
            <div><h1>Monitoramento de NVRs</h1><p>Status de conectividade dos gravadores e cameras.</p></div>
            <div class="refresh">Atualizado: <?= $escape($updatedAt) ?><br>Atualizacao automatica a cada 10 minutos</div>
        </header>

        <?php if ($error): ?>
            <div class="error"><strong>Falha ao carregar o monitoramento.</strong><br><?= $escape($error) ?></div>
        <?php else: ?>
            <section class="summary" aria-label="Resumo">
                <div class="metric"><span>NVRs online</span><strong><?= $onlineNvrs ?> / <?= count($nvrRows) ?></strong></div>
                <div class="metric"><span>Cameras ativas</span><strong><?= array_sum(array_column($nvrRows, 'active')) ?></strong></div>
                <div class="metric"><span>Cameras sem resposta</span><strong><?= $offlineCamerasCount ?></strong></div>
            </section>
            <section class="nvr-list" aria-label="Lista de NVRs">
                <?php foreach ($nvrRows as $nvr): ?>
                    <article class="nvr">
                        <div><div class="nvr-name"><?= $escape($nvr['name']) ?></div><div class="ip"><?= $escape($nvr['ip']) ?></div></div>
                        <div class="status <?= $nvr['online'] ? 'online' : '' ?>"><i class="dot"></i><?= $nvr['online'] ? 'Respondendo' : 'Sem resposta' ?></div>
                        <div><div class="camera-count"><?= $nvr['active'] ?> <small>de <?= $nvr['total'] ?> Cameras ativas</small></div><?php if (!$nvr['offline']): ?><div class="none">Todas as cameras respondendo</div><?php endif; ?></div>
                        <?php if ($nvr['offline']): ?>
                            <div class="offline-cameras">
                                <div class="offline-cameras-title">Cameras sem resposta — clique em uma miniatura para ampliar</div>
                                <div class="camera-thumbnails">
                                    <?php foreach ($nvr['offline'] as $camera): ?>
                                        <?php $imageUrl = 'cameras/' . rawurlencode($camera['ip']) . '.jpg'; ?>
                                        <button class="camera-thumbnail" type="button" data-ip="<?= $escape($camera['ip']) ?>"<?= $camera['image'] ? ' data-image="' . $escape($imageUrl) . '"' : ' disabled title="Imagem nao encontrada"' ?>>
                                            <?php if ($camera['image']): ?><img src="<?= $escape($imageUrl) ?>" alt="Miniatura da camera <?= $escape($camera['ip']) ?>" loading="lazy"><?php else: ?><span class="no-image">Imagem indisponivel</span><?php endif; ?>
                                            <span><?= $escape($camera['ip']) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if (!$nvrRows): ?><div class="error">Nenhum NVR encontrado na tabela `nvrs`.</div><?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <dialog id="image-modal" aria-labelledby="image-modal-title">
        <div class="image-modal-header"><strong id="image-modal-title"></strong><button class="image-modal-close" type="button" aria-label="Fechar imagem">&times;</button></div>
        <div class="image-modal-content"><img id="image-modal-preview" alt=""></div>
        <div class="image-modal-actions"><a id="send-danrley" class="whatsapp-button" target="_blank" rel="noopener">Enviar para Danrley</a><a id="send-gabriel" class="whatsapp-button" target="_blank" rel="noopener">Enviar para Gabriel</a></div>
    </dialog>

    <script>
        const modal = document.getElementById('image-modal');
        const preview = document.getElementById('image-modal-preview');
        const title = document.getElementById('image-modal-title');
        const danrley = document.getElementById('send-danrley');
        const gabriel = document.getElementById('send-gabriel');
        const whatsappLink = (phone, message) => `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;

        document.querySelectorAll('.camera-thumbnail[data-image]').forEach((thumbnail) => {
            thumbnail.addEventListener('click', () => {
                const ip = thumbnail.dataset.ip;
                const imageUrl = new URL(thumbnail.dataset.image, window.location.href);
                imageUrl.hostname = '192.168.0.53';
                const message = `Alerta de câmera sem resposta\nCâmera: ${ip}\nImagem: ${imageUrl.href}`;
                title.textContent = `Camera ${ip}`;
                preview.src = thumbnail.dataset.image;
                preview.alt = `Imagem da camera ${ip}`;
                danrley.href = whatsappLink('5592984648187', message);
                gabriel.href = whatsappLink('559284600984', message);
                modal.showModal();
            });
        });

        document.querySelector('.image-modal-close').addEventListener('click', () => modal.close());
        modal.addEventListener('click', (event) => { if (event.target === modal) modal.close(); });
    </script>
</body>
</html>
