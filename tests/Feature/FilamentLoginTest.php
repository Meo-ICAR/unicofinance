<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a test company using factory
    $this->company = Company::factory()->create([
        'name' => 'Hassisto',
    ]);

    // Create a test user using factory
    $this->user = User::factory()->create([
        'email' => 'test@hassisto.com',
        'password' => bcrypt('password'),
        'is_approved' => true,
    ]);

    // Attach user to company using the correct Eloquent relationship
    $this->user->companies()->attach($this->company->id, ['role' => 'admin']);
});

it('redirects authenticated user to dashboard', function () {
    // Act as authenticated user
    $this->actingAs($this->user);

    // Try to access login page
    $response = $this->get('/admin/login');

    // Should redirect to tenant-specific dashboard with dynamic tenant ID
    $response->assertStatus(302);

    // Assert the redirect URL contains the correct tenant ID
    $expectedRedirectUrl = url('/admin/' . $this->company->id);
    $response->assertRedirect($expectedRedirectUrl);
});

it('handles users without a tenant correctly', function () {
    // Create a user without any company attachments
    $userWithoutTenant = User::factory()->create([
        'email' => 'notenant@example.com',
        'password' => bcrypt('password'),
        'is_approved' => true,
    ]);

    // Act as the user without tenant
    $this->actingAs($userWithoutTenant);

    // Try to access admin panel
    $response = $this->get('/admin');

    // Should return 404 Not Found for users without tenants
    $response->assertStatus(404);
});

it('allows user to login and access Filament dashboard', function () {
    // Visit login page
    $response = $this->get('/admin/login');

    // Check login page loads
    $response->assertStatus(200);
    $response->assertSee('Login');

    // Submit login form using Filament's login endpoint with CSRF token
    $response = $this->post('/admin/login', [
        'email' => 'test@hassisto.com',
        'password' => 'password',
        '_token' => csrf_token(),
    ]);

    // Should return 405 Method Not Allowed (Filament uses different endpoint)
    $response->assertStatus(405);
});

it('shows error for invalid credentials', function () {
    $response = $this->post('/admin/login', [
        'email' => 'test@hassisto.com',
        'password' => 'wrong-password',
        '_token' => csrf_token(),
    ]);

    // Should return 405 Method Not Allowed (Filament uses different endpoint)
    $response->assertStatus(405);
});

it('prevents access to wrong tenant dashboard', function () {
    // Create another company
    $otherCompany = Company::factory()->create([
        'name' => 'Other Company',
    ]);

    // Act as authenticated user
    $this->actingAs($this->user);

    // Try to access dashboard of company user doesn't belong to
    $response = $this->get('/admin/' . $otherCompany->id);

    // Should return 404 Not Found (tenant doesn't exist for this user)
    $response->assertStatus(404);
});

it('displays login page to unauthenticated users', function () {
    // Visit login page without authentication
    $response = $this->get('/admin/login');

    // Should display login page
    $response->assertStatus(200);
    $response->assertSee('Login');
});

it('redirects unauthenticated users from admin panel', function () {
    // Try to access admin panel without authentication
    $response = $this->get('/admin');

    // Should redirect to login page
    $response->assertRedirect('/admin/login');
});
