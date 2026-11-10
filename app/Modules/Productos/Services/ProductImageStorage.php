<?php

namespace App\Modules\Productos\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Fotos locales normalizadas: sin nombres del usuario, SVG ni contenido ejecutable. */
class ProductImageStorage
{
    public function resolve(Request $request, ?string $previous): ?string
    {
        $file = $request->file('imagen_archivo') ?? $request->file('imagen');
        if ($file) {
            validator(['foto' => $file], ['foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=4096,max_height=4096'])->validate();
            $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
            if (! $image) {
                throw ValidationException::withMessages(['imagen_archivo' => 'La fotografía no se pudo decodificar.']);
            }
            $directory = public_path('assets/catalogo');
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                imagedestroy($image);
                throw new \RuntimeException('No se pudo preparar la carpeta de fotografías.');
            }
            $name = 'producto-'.Str::uuid().'.webp';
            try {
                if (! imagewebp($image, $directory.'/'.$name, 85)) {
                    throw new \RuntimeException('No se pudo guardar la fotografía.');
                }
            } finally {
                imagedestroy($image);
            }
            $path = '/assets/catalogo/'.$name;
            $request->attributes->set('new_product_image', $path);
            return $path;
        }
        foreach (['imagen_url', 'imagen'] as $field) {
            if ($request->exists($field)) {
                $value = trim((string) $request->input($field));
                if ($value === '') {
                    return null;
                }
                $local = preg_match('~^/assets/catalogo/[a-zA-Z0-9_./%-]+\.(?:png|jpg|jpeg|webp)$~iD', $value)
                    && ! str_contains(rawurldecode($value), '..');
                if ($local && ! is_file(public_path(ltrim(rawurldecode($value), '/')))) {
                    throw ValidationException::withMessages([$field => 'La fotografía local no existe. Subí un archivo para guardarla.']);
                }
                $parts = parse_url($value);
                $remote = is_array($parts) && ($parts['scheme'] ?? '') === 'https'
                    && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['port'])
                    && in_array(strtolower($parts['host'] ?? ''), config('catalogo.image_hosts', []), true);
                if (! $local && ! $remote) {
                    throw ValidationException::withMessages([$field => 'Usá una fotografía subida o una URL HTTPS de un dominio autorizado.']);
                }
                return $value;
            }
        }
        return $previous;
    }

    public function discardFailedUpload(Request $request): void
    {
        $path = $request->attributes->get('new_product_image');
        if (is_string($path) && preg_match('~^/assets/catalogo/producto-[a-f0-9-]+\.webp$~D', $path)) {
            @unlink(public_path(ltrim($path, '/')));
        }
    }
}
