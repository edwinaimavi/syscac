<?php

use App\Http\Controllers\Admin\MemberController;
use App\Models\CashMovement;
use App\Models\Member;
use App\Models\MemberEnrollment;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function memberRequest(array $overrides = []): Request
{
    return Request::create('/admin/socios', 'POST', array_merge([
        'code' => null, 'first_name' => 'Socio', 'last_name' => 'Prueba', 'dni' => fake()->unique()->numerify('########'),
        'birth_date' => '1990-01-01', 'admission_date' => now()->format('Y-m-d'), 'member_type_selected' => 'nuevo',
        'status' => 'vigente', 'civil_status' => 'soltero', 'enrollment_amount' => '50.00',
        'enrollment_date' => now()->format('Y-m-d'), 'enrollment_payment_method' => 'efectivo',
    ], $overrides));
}

it('creates an old member without validating hidden enrollment controls', function () {
    $request = memberRequest([
        'admission_date' => now()->subYears(2)->format('Y-m-d'), 'member_type_selected' => 'antiguo',
        'enrollment_amount' => 'invalid', 'enrollment_date' => 'invalid',
        'enrollment_payment_method' => 'yape', 'enrollment_payment_reference' => '',
    ]);

    app(MemberController::class)->store($request);

    $member = Member::where('dni', $request->input('dni'))->firstOrFail();
    expect($member->member_type)->toBe('antiguo')
        ->and($member->enrollments()->count())->toBe(0)
        ->and(MemberEnrollment::count())->toBe(0)
        ->and(CashMovement::where('category', 'inscripcion_socio')->count())->toBe(0)
        ->and(Receipt::where('type', 'inscripcion_socio')->count())->toBe(0);
});

it('updates one enrollment and its integrations without duplicates', function () {
    $create = memberRequest();
    app(MemberController::class)->store($create);
    $member = Member::where('dni', $create->input('dni'))->firstOrFail();

    $update = memberRequest([
        'dni' => $member->dni, 'first_name' => $member->first_name, 'last_name' => $member->last_name,
        'enrollment_payment_method' => 'yape', 'enrollment_payment_reference' => 'YP-123',
    ]);
    app(MemberController::class)->update($update, $member);

    expect($member->enrollments()->where('status', 'registrado')->count())->toBe(1)
        ->and(CashMovement::where('related_type', MemberEnrollment::class)->count())->toBe(1)
        ->and(Receipt::where('related_type', MemberEnrollment::class)->count())->toBe(1)
        ->and($member->enrollments()->first()->payment_reference)->toBe('YP-123');
});

it('keeps the current voucher and can replace an image with a pdf', function () {
    Storage::fake('public');
    $create = memberRequest();
    $create->files->set('enrollment_voucher', UploadedFile::fake()->image('voucher.jpg'));
    app(MemberController::class)->store($create);
    $member = Member::where('dni', $create->input('dni'))->firstOrFail();
    $originalPath = $member->enrollments()->firstOrFail()->voucher_path;
    Storage::disk('public')->assertExists($originalPath);
    $imagePayload = app(MemberController::class)->show($member)->getData(true)['enrollment'];
    expect($imagePayload['voucher_is_image'])->toBeTrue()
        ->and($imagePayload['voucher_is_pdf'])->toBeFalse()
        ->and($imagePayload['voucher_public_url'])->toStartWith('/storage/member-enrollments/');

    $withoutReplacement = memberRequest(['dni' => $member->dni]);
    app(MemberController::class)->update($withoutReplacement, $member);
    expect($member->enrollments()->first()->voucher_path)->toBe($originalPath);

    $withPdf = memberRequest(['dni' => $member->dni]);
    $withPdf->files->set('enrollment_voucher', UploadedFile::fake()->createWithContent('voucher.pdf', '%PDF-1.4 test'));
    app(MemberController::class)->update($withPdf, $member->fresh());
    $newPath = $member->enrollments()->first()->voucher_path;

    expect($newPath)->not->toBe($originalPath)->and($newPath)->toEndWith('.pdf')
        ->and($member->enrollments()->count())->toBe(1);
    Storage::disk('public')->assertExists($newPath);
    $pdfPayload = app(MemberController::class)->show($member->fresh())->getData(true)['enrollment'];
    expect($pdfPayload['voucher_is_pdf'])->toBeTrue()
        ->and($pdfPayload['voucher_public_url'])->toStartWith('/storage/member-enrollments/');
});
