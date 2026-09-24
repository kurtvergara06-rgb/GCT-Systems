"""Regression test for PyMySQL queries containing literal percent signs."""

from operation_ai.ml.database import DbConnection


class FakeCursor:
    def __init__(self):
        self.calls = []

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc, tb):
        return False

    def execute(self, *args):
        self.calls.append(args)

    def fetchall(self):
        return []


class FakeConnection:
    def __init__(self, cursor):
        self._cursor = cursor

    def cursor(self):
        return self._cursor


def make_db(cursor):
    db = DbConnection.__new__(DbConnection)
    db.cfg = {}
    db._conn = FakeConnection(cursor)
    return db


literal_cursor = FakeCursor()
literal_db = make_db(literal_cursor)
literal_sql = "SELECT 1 WHERE 'DEMO-123' LIKE 'DEMO-%'"
literal_db.query(literal_sql)
assert literal_cursor.calls == [(literal_sql,)]

param_cursor = FakeCursor()
param_db = make_db(param_cursor)
param_sql = "SELECT 1 WHERE %s = %s"
param_db.query(param_sql, (1, 1))
assert param_cursor.calls == [(param_sql, (1, 1))]

print("DB query literal-percent regression checks passed.")
