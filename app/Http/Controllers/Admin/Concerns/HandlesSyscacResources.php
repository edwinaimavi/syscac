<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;

trait HandlesSyscacResources
{
    protected function validationMessages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'exists' => 'Seleccione un :attribute valido.',
            'unique' => 'El campo :attribute ya esta registrado.',
            'date' => 'El campo :attribute debe ser una fecha valida.',
            'numeric' => 'El campo :attribute debe ser un numero valido.',
            'integer' => 'El campo :attribute debe ser un numero entero.',
            'min' => 'El campo :attribute debe ser mayor o igual a :min.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'in' => 'Seleccione un valor valido para :attribute.',
            'image' => 'La foto debe ser una imagen valida.',
            'mimes' => 'El archivo debe tener un formato valido.',
            'file' => 'El comprobante debe ser un archivo valido.',
            'size' => 'El DNI debe tener :size digitos.',
            '*.required' => 'El campo :attribute es obligatorio.',
            '*.exists' => 'Seleccione un :attribute valido.',
            '*.unique' => 'El campo :attribute ya esta registrado.',
            '*.date' => 'El campo :attribute debe ser una fecha valida.',
            '*.numeric' => 'El campo :attribute debe ser un numero valido.',
            '*.integer' => 'El campo :attribute debe ser un numero entero.',
            '*.min' => 'El campo :attribute debe ser mayor o igual a :min.',
            '*.max' => 'El campo :attribute no debe superar :max caracteres.',
            '*.in' => 'Seleccione un valor valido para :attribute.',
            '*.image' => 'La foto debe ser una imagen valida.',
            '*.mimes' => 'El archivo debe tener un formato valido.',
            '*.file' => 'El comprobante debe ser un archivo valido.',
            '*.size' => 'El DNI debe tener :size digitos.',
            'dni.required' => 'El DNI es obligatorio.',
            'dni.size' => 'El DNI debe tener 8 digitos.',
            'dni.unique' => 'El DNI ya esta registrado.',
            'admission_date.required' => 'La fecha de ingreso es obligatoria.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'member_id.required' => 'Seleccione un socio valido.',
            'member_id.exists' => 'Seleccione un socio valido.',
            'photo_path.image' => 'La foto debe ser una imagen valida.',
            'voucher_path.file' => 'El comprobante debe ser un archivo valido.',
            'voucher_path.max' => 'El comprobante no debe superar el tamano permitido.',
            'file_path.file' => 'El archivo del recibo debe ser valido.',
        ];
    }

    protected function storeUploadedFile(Request $request, array &$data, string $field, string $directory): void
    {
        if ($request->hasFile($field)) {
            $data[$field] = $request->file($field)->store($directory, 'public');
        }
    }

    protected function applyAuditFields(array &$data): void
    {
        if (auth()->check()) {
            $data['created_by'] = $data['created_by'] ?? auth()->id();
            $data['updated_by'] = auth()->id();
        }
    }
}
