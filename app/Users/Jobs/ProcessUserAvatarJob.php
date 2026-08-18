<?php

namespace App\Users\Jobs;

use App\Auth\Models\Usuario;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProcessUserAvatarJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly string $usuarioId,
        public readonly string $originalPath,
    ) {}

    public function handle(): void
    {
        $usuario = Usuario::query()->find($this->usuarioId);

        if ($usuario === null) {
            return;
        }

        $disk = (string) config('filesystems.object_disk', 's3');
        $contents = Storage::disk($disk)->get($this->originalPath);

        if ($contents === null || $contents === '') {
            throw new RuntimeException('Avatar original não encontrado no Object Storage.');
        }

        $thumbnail = $this->makeThumbnail($contents);
        $thumbPath = $this->thumbnailPath($this->originalPath);

        Storage::disk($disk)->put($thumbPath, $thumbnail, 'public');

        $usuario->forceFill([
            'foto' => $thumbPath,
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
            throw new RuntimeException('Imagem de avatar inválida.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $max = 256;
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
