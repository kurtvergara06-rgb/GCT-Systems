import fitz


def extract_pdf_text(pdf_path: str) -> str:
    document = fitz.open(pdf_path)

    extracted_pages = []

    for page in document:
        page_text = page.get_text("text")

        if page_text:
            extracted_pages.append(page_text)

    document.close()

    return "\n".join(extracted_pages).strip()