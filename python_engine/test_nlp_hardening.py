import os
import tempfile
import unittest

from fastapi import HTTPException

from NLP.pdf_extractor import extract_pdf_rows, extract_pdf_text
from NLP.text_cleaner import clean_text


class NlpHardeningTest(unittest.TestCase):
    def test_corrupted_pdf_is_rejected_as_bad_request(self):
        with tempfile.NamedTemporaryFile(suffix=".pdf", delete=False) as handle:
            handle.write(b"NOT A VALID PDF")
            path = handle.name

        try:
            for extractor in (extract_pdf_text, extract_pdf_rows):
                with self.subTest(extractor=extractor.__name__):
                    with self.assertRaises(HTTPException) as context:
                        extractor(path)

                    self.assertEqual(400, context.exception.status_code)
                    self.assertEqual(
                        "Corrupted or unreadable PDF document.",
                        context.exception.detail,
                    )
        finally:
            os.unlink(path)

    def test_clean_text_preserves_newlines_and_tabs(self):
        source = "Bus No:\tGCT-101\nRoute:\tLipa - Batangas\x00\x07\nStatus: Active"

        cleaned = clean_text(source)

        self.assertEqual(
            "Bus No: GCT-101\nRoute: Lipa - Batangas\nStatus: Active",
            cleaned,
        )
        self.assertEqual(2, cleaned.count("\n"))
        self.assertNotIn("\x00", cleaned)
        self.assertNotIn("\x07", cleaned)

    def test_clean_text_keeps_non_label_tabs(self):
        cleaned = clean_text("Column A\tColumn B\nValue A\tValue B")

        self.assertEqual(
            "Column A\tColumn B\nValue A\tValue B",
            cleaned,
        )


if __name__ == "__main__":
    unittest.main()
