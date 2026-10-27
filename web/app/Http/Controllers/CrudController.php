<?php

namespace App\Http\Controllers;

use App\Support\Admin\Audit;
use Illuminate\Http\Request;

/**
 * Generic list / create / edit / delete-restore screens for master data (plus
 * activate-deactivate, but only for resources that opt in — currently users only).
 *
 * Delete is a soft delete (sets deleted_at): the row disappears from lists and dropdowns
 * but the row itself, and anything that already references it (sales, tickets, ...), is
 * untouched. A deleted row can be restored.
 *
 * A subclass describes one resource; permissions are enforced by the routes.
 */
abstract class CrudController extends Controller
{
    /** Eloquent model class. */
    abstract protected function model();

    /** Route name prefix, e.g. "users". */
    abstract protected function route();

    /** Permission prefix, e.g. "users" (routes use "<perm>.view" / "<perm>.manage"). */
    abstract protected function perm();

    /** Menu label key, used for the page title. */
    abstract protected function title();

    /**
     * Form fields: name => ['type' => text|number|password|select, 'options' => [value => label], 'step' => '0.01'].
     */
    abstract protected function fields();

    /** The language file with the labels of this screen's fields (a key per field name). */
    abstract protected function langFile();

    /** Validation rules for the given model (new or existing). */
    abstract protected function rules($model);

    /** Own texts for validation messages ('field.rule' => text); none by default. */
    protected function messages()
    {
        return [];
    }

    /** Adjust validated data before saving (hash passwords, derive columns...). */
    protected function prepare(array $data, $model)
    {
        return $data;
    }

    /** True for a resource that is only listed here (its rows are made elsewhere): no New, Edit, Delete, trash or restore. */
    protected function readOnly()
    {
        return false;
    }

    /** True for the (currently one) resource that still has an active/deactivate toggle: users. */
    protected function hasActiveToggle()
    {
        return false;
    }

    /** The rows this user may reach (every row by default); a record outside it is not found. */
    protected function query()
    {
        $class = $this->model();

        return $class::query();
    }

    /** Same guard covers deactivate and delete: nobody removes their own account, the Self customer, etc. */
    protected function canModify($model)
    {
        return true;
    }

    public function index(Request $request)
    {
        $class = $this->model();
        $trashed = $request->boolean('trashed');
        $key = (new $class)->getKeyName(); // not always 'id' — e.g. FuelType is keyed by 'fuel_type'

        return view('crud.index', $this->viewData([
            'rows' => $trashed ? $this->query()->onlyTrashed()->orderBy($key)->get() : $this->query()->orderBy($key)->get(),
            'trashed' => $trashed,
            'hasActive' => $this->hasActiveToggle(),
            'modifiable' => function ($row) {
                return $this->canModify($row);
            },
        ]));
    }

    public function create()
    {
        $class = $this->model();

        return view('crud.form', $this->viewData(['record' => new $class]));
    }

    public function store(Request $request)
    {
        $class = $this->model();
        $record = new $class;
        $data = $this->prepare($request->validate($this->rules($record), $this->messages()), $record);

        $record->forceFill($data);
        $changes = $this->changes($record, true);
        $record->save();
        $this->audit('create', $record, $changes);

        return $this->done($record, 'created');
    }

    public function edit($id)
    {
        $class = $this->model();

        return view('crud.form', $this->viewData(['record' => $this->query()->findOrFail($id)]));
    }

    public function update(Request $request, $id)
    {
        $class = $this->model();
        $record = $this->query()->findOrFail($id);
        $data = $this->prepare($request->validate($this->rules($record), $this->messages()), $record);

        $record->forceFill($data);
        $changes = $this->changes($record, false);
        $record->save();
        $this->audit('update', $record, $changes);

        return $this->done($record, 'saved');
    }

    public function toggle($id)
    {
        abort_unless($this->hasActiveToggle(), 404);

        $class = $this->model();
        $record = $this->query()->findOrFail($id);
        abort_unless($this->canModify($record), 403);

        $record->active = !$record->active;
        $record->save();
        $this->audit($record->active ? 'activate' : 'deactivate', $record);

        return redirect()->route($this->route() . '.index')->with('status', __($this->langFile() . '.saved'));
    }

    public function destroy($id)
    {
        $class = $this->model();
        $record = $this->query()->findOrFail($id);
        abort_unless($this->canModify($record), 403);

        $record->delete();
        $this->audit('delete', $record);

        return redirect()->route($this->route() . '.index')->with('status', __($this->langFile() . '.deleted'));
    }

    public function restore($id)
    {
        $class = $this->model();
        $record = $this->query()->onlyTrashed()->findOrFail($id);

        $record->restore();
        $this->audit('restore', $record);

        return redirect()->route($this->route() . '.index', ['trashed' => 1])->with('status', __($this->langFile() . '.restored'));
    }

    private function done($record, $message)
    {
        return redirect()->route($this->route() . '.index')->with('status', __($this->langFile() . '.' . $message));
    }

    private function audit($action, $record, $changes = '')
    {
        $label = $record->name ?? $record->getKey();
        Audit::log($this->route() . '_' . $action, '#' . $record->getKey() . ' ' . $label . ($changes !== '' ? ': ' . $changes : ''));
    }

    // What a save changes, for the audit line: "price 5 -> 6, role cashier -> manager" (a new record: "price 6, role cashier"). A password or PIN is only said to have
    // changed; the bookkeeping columns are left out.
    private function changes($record, $new)
    {
        $secret = ['password', 'pos_salt', 'pos_hash', 'pin_salt', 'pin_hash'];
        $skip = ['updated_at', 'created_at', 'synced'];
        $parts = [];
        $secretChanged = false;
        foreach ($record->getDirty() as $key => $value) {
            if (in_array($key, $secret, true)) {
                $secretChanged = true;
            } elseif (!in_array($key, $skip, true)) {
                $parts[] = $new ? $key . ' ' . $value : $key . ' ' . ($record->getOriginal($key) ?? '-') . ' -> ' . ($value ?? '-');
            }
        }
        if ($secretChanged) {
            $parts[] = 'password or PIN changed';
        }

        return implode(', ', $parts);
    }

    private function viewData(array $extra)
    {
        return $extra + [
            'title' => $this->title(),
            'route' => $this->route(),
            'perm' => $this->perm(),
            'fields' => $this->fields(),
            'langFile' => $this->langFile(),
            'readOnly' => $this->readOnly(),
            'fieldLabel' => function ($name) {
                return __($this->langFile() . '.' . $name);
            },
        ];
    }
}
