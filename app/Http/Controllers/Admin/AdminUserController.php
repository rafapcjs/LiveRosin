<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPasswordRequest;
use App\Http\Requests\Admin\AdminUserRequest;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Administradores del panel: ver, crear, cambiar contraseña y eliminar.
 * Cada cambio pide la contraseña actual de quien lo hace y queda en el registro de seguridad.
 */
class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()->orderByRaw('COALESCE(username, email)')->get();

        return view('admin.users.index', ['users' => $users, 'me' => $request->user()]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(AdminUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['username'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
        ]);

        SecurityEvent::record(SecurityEvent::ADMIN_CREATED, $request, $user->displayName(),
            $request->user()->displayName().' creó al administrador '.$user->displayName());

        return redirect()->route('admin.administradores.index')
            ->with('success', "Se creó el administrador «{$user->displayName()}». Ya puede entrar con su usuario y contraseña.");
    }

    public function editPassword(User $user, Request $request): View
    {
        return view('admin.users.password', ['user' => $user, 'isMe' => $user->is($request->user())]);
    }

    public function updatePassword(AdminPasswordRequest $request, User $user): RedirectResponse
    {
        $me = $request->user();
        $isMe = $user->is($me);

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
            'remember_token' => null,
        ])->save();

        // Cierra las sesiones abiertas de esa cuenta en otros equipos (la tuya actual sigue abierta).
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        SecurityEvent::record(SecurityEvent::PASSWORD_CHANGED, $request, $user->displayName(),
            $isMe ? $me->displayName().' cambió su propia contraseña'
                  : $me->displayName().' cambió la contraseña de '.$user->displayName());

        return redirect()->route('admin.administradores.index')
            ->with('success', $isMe
                ? 'Cambiaste tu contraseña. Úsala la próxima vez que entres.'
                : "Se cambió la contraseña de «{$user->displayName()}». Sus sesiones abiertas se cerraron.");
    }

    /** Habilita o deshabilita un administrador (un deshabilitado no puede entrar). */
    public function toggle(Request $request, User $user): RedirectResponse
    {
        $this->confirmPassword($request);

        if ($user->is($request->user())) {
            return back()->with('error', 'No puedes deshabilitar tu propia cuenta.');
        }

        $enable = ! $user->is_active;

        if (! $enable && User::where('is_active', true)->count() <= 1) {
            return back()->with('error', 'Debe quedar al menos un administrador habilitado.');
        }

        $user->forceFill(['is_active' => $enable])->save();

        if (! $enable) {
            // Lo saca del panel en cualquier equipo donde lo tenga abierto.
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $me = $request->user()->displayName();
        SecurityEvent::record($enable ? SecurityEvent::ADMIN_ENABLED : SecurityEvent::ADMIN_DISABLED, $request, $user->displayName(),
            $me.($enable ? ' habilitó a ' : ' deshabilitó a ').$user->displayName());

        return redirect()->route('admin.administradores.index')->with('success', $enable
            ? "Se habilitó a «{$user->displayName()}». Ya puede volver a entrar."
            : "Se deshabilitó a «{$user->displayName()}». Ya no puede entrar al panel.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->confirmPassword($request);

        if ($user->is($request->user())) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta. Pídeselo a otro administrador.');
        }

        if (User::count() <= 1 || ($user->is_active && User::where('is_active', true)->count() <= 1)) {
            return back()->with('error', 'Debe quedar al menos un administrador habilitado.');
        }

        $name = $user->displayName();

        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        SecurityEvent::record(SecurityEvent::ADMIN_DELETED, $request, $name,
            $request->user()->displayName().' eliminó al administrador '.$name);

        return redirect()->route('admin.administradores.index')->with('success', "Se eliminó el administrador «{$name}».");
    }

    /** Pide la contraseña actual de quien hace el cambio. */
    private function confirmPassword(Request $request): void
    {
        $request->validate(
            ['current_password' => ['required', 'current_password']],
            [
                'current_password.required' => 'Escribe tu contraseña actual para confirmar.',
                'current_password.current_password' => 'Tu contraseña actual no es correcta.',
            ],
        );
    }
}
