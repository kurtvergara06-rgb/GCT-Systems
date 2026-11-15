import re


def clean_text(text: str) -> str:
    text = text.replace("\r", "\n")
    text = text.replace("\ufeff", "")
    text = re.sub(r"\n+", "\n", text)
    text = re.sub(r" +", " ", text)
    text = re.sub(r"[ \t]*:[ \t]*", ": ", text)
    # Remove non-printable control characters without destroying document
    # structure. Newlines and tabs are kept for downstream NLP parsing.
    text = re.sub(r"[\x00-\x08\x0b\x0c\x0e-\x1f]", "", text)
    return text.strip()
