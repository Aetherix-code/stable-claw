<?php

namespace App\Services\Connections\Contracts;

interface MailConnector
{
    /**
     * List recent emails from the inbox.
     *
     * @return array<int, array{uid: int, subject: string, from: string, date: string, snippet: string, is_read: bool}>
     */
    public function list(int $limit = 15): array;

    /**
     * Read a specific email by UID.
     *
     * @return array{uid: int, subject: string, from: string, to: string, date: string, body: string, attachments: array<int, string>}
     */
    public function read(int $uid): array;

    /**
     * Search emails by query.
     *
     * @return array<int, array{uid: int, subject: string, from: string, date: string, snippet: string}>
     */
    public function search(string $query, int $limit = 15): array;

    /**
     * Create a draft email.
     *
     * @return array{success: bool, message: string}
     */
    public function draft(string $to, string $subject, string $body): array;
}
