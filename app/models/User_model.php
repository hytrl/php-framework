<?php
defined('BASEPATH') or exit('No direct script access allowed');

class User_model extends BaseModel {

    public function get_all(int $limit = 50, int $offset = 0): array {

        return $this->db
            ->get('SELECT id, email, created_at FROM users ORDER BY id DESC LIMIT ? OFFSET ?', [$limit, $offset])
            ->result();

    }

    public function find(int $id): ?object {

        return $this->db->get('SELECT id, email, created_at FROM users WHERE id = ?', [$id])->row();

    }

    public function create(string $email, string $passwordHash): int {

        $this->db->query('INSERT INTO users (email, password) VALUES (:email, :password)', [
            'email'    => $email,
            'password' => $passwordHash,
        ]);

        return (int) $this->db->insert_id();

    }

    public function update_email(int $id, string $email): int {

        $this->db->query('UPDATE users SET email = ? WHERE id = ?', [$email, $id]);

        return $this->db->affected_rows();

    }

    public function delete(int $id): int {

        $this->db->query('DELETE FROM users WHERE id = ?', [$id]);

        return $this->db->affected_rows();

    }

    public function count(): int {

        return (int) $this->db->get('SELECT COUNT(*) FROM users')->scalar();

    }

}
