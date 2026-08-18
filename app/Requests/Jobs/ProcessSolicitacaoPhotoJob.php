<?php

namespace App\Requests\Jobs;

use App\Requests\FotoSolicitacao;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProcessSolicitacaoPhotoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly string $fotoId,
        public readonly string $originalPath,
    ) {}

    public function handle(): void
    {
        $foto = FotoSolicitacao::query()->find($this->fotoId);

        if ($foto === null) {
            return;
        }

        $disk = (string) config('filesystems.object_disk', 's3');
        $contents = Storage::disk($disk)->get($this->originalPath);

        if ($contents === null || $contents === '') {
            throw new RuntimeException('Foto original da solicitação não encontrada no Object Storage.');
        }

        $thumbnail = $this->makeThumbnail($contents);
        $thumbPath = $this->thumbnailPath($this->originalPath);

        Storage::disk($disk)->put($thumbPath, $thumbnail, 'public');

        $foto->forceFill([
            'url' => $thumbPath,
        ])->save();
    }

    private function thumbnailPath(string $originalPath): string
    {
        $directory = dirname($originalPath);
        $filename = pathinfo($originalPath, PATHINFO_FILENAME);

        return $directory.'/'.$filename.'_thumb.jpg';
    }

    private function makeThumbnail(string $binary): string
    {
        if (! extension_loaded('gd')) {
            return $binary;
        }

        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            throw new RuntimeException('Imagem da solicitação inválida.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $max = 1280;
        $scale = min($max / max($width, 1), $max / max($height, 1), 1.0);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $white);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagejpeg($thumb, null, 82);
        $data = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($thumb);

        return $data;
    }
}
