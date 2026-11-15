<?php

    namespace App\Http\Controllers\Admin;

    use App\Http\Controllers\Controller;
    use App\Models\User;
    use Illuminate\Http\Request;
    use Illuminate\Validation\Rule;
    use Illuminate\Support\Facades\Hash;

    class AdminUserController extends Controller
    {
        private array $departments = [
            'Maintenance',
            'Warehouse',
            'Purchase',
            'Operation',
        ];

        private array $roles = [
            'head' => 'Head',
            'staff' => 'Staff',
        ];

        private array $statuses = [
            'Active',
            'Inactive',
            'Pending',
        ];

        public function index(Request $request)
        {
            $query = User::query();

            if ($request->filled('search')) {
                $search = trim($request->search);

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            }

            if ($request->filled('department') && $request->department !== 'All Departments') {
                $query->where('department', $request->department);
            }

            if ($request->filled('role') && $request->role !== 'All Roles') {
                $query->where('role', $request->role);
            }

            if ($request->filled('status') && $request->status !== 'All Status') {
                $query->where('status', $request->status);
            }

            $users = $query
                ->orderByDesc('created_at')
                ->paginate(10)
                ->withQueryString();

            $totalUsers = User::count();
            $activeUsers = User::where('status', 'Active')->count();
            $inactiveUsers = User::where('status', 'Inactive')->count();
            $pendingUsers = User::where('status', 'Pending')->count();

            $departments = $this->departments;
            $roles = $this->roles;
            $statuses = $this->statuses;

            return view('Admin.users', compact(
                'users',
                'totalUsers',
                'activeUsers',
                'inactiveUsers',
                'pendingUsers',
                'departments',
                'roles',
                'statuses'
            ));
        }

        public function store(Request $request)
        {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:6'],
                'department' => ['required', 'string', Rule::in($this->departments)],
                'role' => ['required', 'string', Rule::in(array_keys($this->roles))],
                'status' => ['required', 'string', Rule::in($this->statuses)],
            ]);

            User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'department' => $validated['department'],
                'role' => $validated['role'],
                'status' => $validated['status'],
            ]);

            return redirect()
                ->route('admin.users')
                ->with('success', 'User account created successfully.');
        }

        public function update(Request $request, User $user)
        {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
                'department' => ['required', 'string', Rule::in($this->departments)],
                'role' => ['required', 'string', Rule::in(array_keys($this->roles))],
                'status' => ['required', 'string', Rule::in($this->statuses)],
                'password' => ['nullable', 'string', 'min:6'],
            ]);

            $updateData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'department' => $validated['department'],
                'role' => $validated['role'],
                'status' => $validated['status'],
            ];

            if (! empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $user->update($updateData);

            return redirect()
                ->route('admin.users')
                ->with('success', 'User account updated successfully.');
        }

        public function updateStatus(Request $request, User $user)
        {
            $validated = $request->validate([
                'status' => ['required', 'string', Rule::in($this->statuses)],
            ]);

            $user->update([
                'status' => $validated['status'],
            ]);

            return redirect()
                ->route('admin.users')
                ->with('success', 'User status updated successfully.');
        }

        public function resetPassword(Request $request, User $user)
        {
            $validated = $request->validate([
                'password' => ['required', 'string', 'min:6', 'confirmed'],
            ]);

            $user->update([
                'password' => Hash::make($validated['password']),
            ]);

            return redirect()
                ->route('admin.users')
                ->with('success', 'Password reset successfully.');
        }

        public function destroy(User $user)
        {
            $user->delete();

            return redirect()
                ->route('admin.users')
                ->with('success', 'User deleted successfully.');
        }
    }