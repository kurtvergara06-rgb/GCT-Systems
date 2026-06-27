import fitz
import re


def extract_pdf_text(pdf_path: str) -> str:
    document = fitz.open(pdf_path)
    extracted_pages = []

    for page in document:
        page_text = page.get_text("text")
        if page_text:
            extracted_pages.append(page_text)

        tables = page.find_tables()
        if tables:
            for table in tables:
                rows = table.extract()
                if rows:
                    extracted_pages.append("\n".join(" | ".join(str(cell or "").strip() for cell in row) for row in rows))

    document.close()
    return "\n".join(extracted_pages).strip()


def is_key_value_table(rows: list) -> bool:
    if not rows or len(rows) < 2:
        return False

    for row in rows:
        if not row or len(row) == 0:
            continue
        if len(row) % 2 != 0:
            return False

        for i, cell in enumerate(row):
            cell_text = str(cell or "").strip()
            if not cell_text:
                continue

            if i % 2 == 0:
                if any(char.isdigit() for char in cell_text) and not any(char.isalpha() for char in cell_text):
                    return False

    return True


def extract_pdf_key_value_table(rows: list) -> dict:
    result = {}

    for row in rows:
        if not row:
            continue

        for i in range(0, len(row) - 1, 2):
            label = re.sub(r"\s+", " ", str(row[i] or "")).strip()
            value = re.sub(r"\s+", " ", str(row[i + 1] or "")).strip()

            if label and value:
                label_key = re.sub(r"[^\w]+", " ", label).strip().lower()
                result[label_key] = value

    return result


def extract_pdf_rows(pdf_path: str) -> tuple[list, str]:
    document = fitz.open(pdf_path)
    rows = []
    table_type = None

    for page_number, page in enumerate(document, start=1):
        tables = page.find_tables()
        for table in tables:
            raw_rows = table.extract()
            if not raw_rows:
                continue

            if is_key_value_table(raw_rows):
                kv_dict = extract_pdf_key_value_table(raw_rows)
                if kv_dict:
                    rows.append({"page": page_number, "type": "key_value", "values": kv_dict})
                    if table_type is None:
                        table_type = "key_value_table"
            else:
                for row in raw_rows:
                    cleaned_row = [re.sub(r"\s+", " ", str(cell or "")).strip() for cell in row]
                    if any(cleaned_row):
                        rows.append({"page": page_number, "type": "standard", "values": cleaned_row})
                        if table_type is None:
                            table_type = "standard_data_table"

    document.close()
    return rows, table_type or None
