<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataController extends Controller
{
    /**
     * Show the data settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('system/Data');
    }

    /**
     * Download the SQLite database file as a backup.
     */
    public function export(): BinaryFileResponse
    {
        $path = DB::connection('sqlite')->getDatabaseName();
        $filename = 'secretary-backup-'.now()->format('Y-m-d').'.sqlite';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/x-sqlite3',
        ]);
    }

    /**
     * Import a SQLite database backup, replacing the current database.
     */
    public function import(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:102400'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $uploaded = $request->file('file');

        if ($uploaded->getClientOriginalExtension() !== 'sqlite') {
            return back()->withErrors(['file' => 'The file must be a .sqlite file.']);
        }

        // Validate that the uploaded file is a real SQLite database
        $uploadedPath = $uploaded->getRealPath();

        try {
            $pdo = new \PDO('sqlite:'.$uploadedPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' LIMIT 1");

            if ($result->fetch() === false) {
                return back()->withErrors(['file' => 'The uploaded file does not contain any tables.']);
            }

            unset($pdo);
        } catch (\PDOException) {
            return back()->withErrors(['file' => 'The uploaded file is not a valid SQLite database.']);
        }

        $dbPath = DB::connection('sqlite')->getDatabaseName();

        DB::disconnect('sqlite');

        copy($uploadedPath, $dbPath);

        return back()->with('success', 'Database restored successfully.');
    }
}
