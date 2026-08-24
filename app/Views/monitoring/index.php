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
    <style>
        :root{color-scheme:dark;--bg:#101724;--panel:#182232;--line:#2b394d;--text:#edf2f8;--muted:#9dacbf;--ok:#35c58a;--bad:#f06a72;--warn:#efa84e}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.45 Arial,sans-serif}main{width:min(1200px,calc(100% - 32px));margin:0 auto;padding:34px 0 48px}header{display:flex;justify-content:space-between;gap:20px;align-items:end;margin-bottom:28px}h1{font-size:26px;margin:0}p{margin:5px 0 0;color:var(--muted)}.refresh{color:var(--muted);font-size:13px;text-align:right}.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}.metric,.nvr{background:var(--panel);border:1px solid var(--line);border-radius:7px}.metric{padding:16px}.metric span{display:block;color:var(--muted);font-size:13px}.metric strong{display:block;font-size:27px;margin-top:3px}.nvr-list{display:grid;gap:12px}.nvr{padding:18px;display:grid;grid-template-columns:minmax(200px,1.3fr) 155px minmax(220px,1fr);gap:20px;align-items:center}.nvr-name{font-weight:700;font-size:17px}.ip{color:var(--muted);font-family:Consolas,monospace;font-size:13px;margin-top:3px}.status{display:inline-flex;align-items:center;gap:7px;font-weight:700}.dot{width:9px;height:9px;border-radius:50%;background:var(--bad)}.online .dot{background:var(--ok)}.camera-count{font-size:21px;font-weight:700}.camera-count small{font-size:13px;font-weight:400;color:var(--muted)}.none{color:var(--ok);font-size:13px;margin-top:5px}.offline-cameras{grid-column:1/-1;border-top:1px solid var(--line);padding-top:15px}.offline-cameras-title{color:var(--bad);font-size:13px;font-weight:700;margin-bottom:10px}.camera-thumbnails{display:flex;flex-wrap:wrap;gap:12px}.camera-thumbnail{width:148px;padding:0;overflow:hidden;color:inherit;text-align:left;background:#111a27;border:1px solid var(--line);border-radius:6px;cursor:pointer}.camera-thumbnail:hover,.camera-thumbnail:focus-visible{border-color:var(--warn);outline:0}.camera-thumbnail img,.camera-thumbnail .no-image{display:block;width:100%;height:92px;object-fit:cover}.camera-thumbnail .no-image{display:grid;place-items:center;color:var(--muted);font-size:12px;background:#222d3e}.camera-thumbnail span{display:block;padding:7px 8px;font:12px Consolas,monospace}dialog{width:min(1000px,calc(100% - 28px));max-height:calc(100% - 28px);padding:0;overflow:hidden;color:var(--text);background:var(--panel);border:1px solid var(--line);border-radius:8px}dialog::backdrop{background:rgb(0 0 0 / 75%)}.image-modal-header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px}.image-modal-header strong{font-family:Consolas,monospace}.image-modal-close{color:var(--text);background:transparent;border:0;font-size:26px;cursor:pointer}.image-modal-content{display:grid;place-items:center;max-height:calc(100vh - 100px);background:#090e15}.image-modal-content img{display:block;max-width:100%;max-height:calc(100vh - 100px);object-fit:contain}.error{background:#40242a;border:1px solid #8b3b47;padding:16px;border-radius:7px;color:#ffccd0}@media(max-width:700px){main{width:min(100% - 24px,1200px);padding-top:22px}header,.nvr{display:block}.refresh{text-align:left;margin-top:12px}.summary{grid-template-columns:1fr}.nvr>*+*{margin-top:15px}}
    </style>
</head>
<body>
    <main>
        <header><div><h1>Monitoramento de NVRs</h1><p>Status de conectividade dos gravadores e cameras.</p></div><div class="refresh">Atualizado: <?= $escape($updatedAt) ?><br>Atualizacao automatica a cada 10 minutos</div></header>
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
                            <div class="offline-cameras"><div class="offline-cameras-title">Cameras sem resposta — clique em uma miniatura para ampliar</div><div class="camera-thumbnails">
                                <?php foreach ($nvr['offline'] as $camera): ?>
                                    <?php $imageUrl = 'cameras/' . rawurlencode($camera['ip']) . '.jpg'; ?>
                                    <button class="camera-thumbnail" type="button" data-ip="<?= $escape($camera['ip']) ?>"<?= $camera['image'] ? ' data-image="' . $escape($imageUrl) . '"' : ' disabled title="Imagem nao encontrada"' ?>>
                                        <?php if ($camera['image']): ?><img src="<?= $escape($imageUrl) ?>" alt="Miniatura da camera <?= $escape($camera['ip']) ?>" loading="lazy"><?php else: ?><span class="no-image">Imagem indisponivel</span><?php endif; ?><span><?= $escape($camera['ip']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if (!$nvrRows): ?><div class="error">Nenhum NVR encontrado na tabela `nvrs`.</div><?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
    <dialog id="image-modal" aria-labelledby="image-modal-title"><div class="image-modal-header"><strong id="image-modal-title"></strong><button class="image-modal-close" type="button" aria-label="Fechar imagem">&times;</button></div><div class="image-modal-content"><img id="image-modal-preview" alt=""></div></dialog>
    <script>
        const modal=document.getElementById('image-modal'),preview=document.getElementById('image-modal-preview'),title=document.getElementById('image-modal-title');
        document.querySelectorAll('.camera-thumbnail[data-image]').forEach((thumbnail)=>thumbnail.addEventListener('click',()=>{const ip=thumbnail.dataset.ip;title.textContent=`Camera ${ip}`;preview.src=thumbnail.dataset.image;preview.alt=`Imagem da camera ${ip}`;modal.showModal()}));
        document.querySelector('.image-modal-close').addEventListener('click',()=>modal.close());modal.addEventListener('click',(event)=>{if(event.target===modal)modal.close()});
    </script>
</body>
</html>
