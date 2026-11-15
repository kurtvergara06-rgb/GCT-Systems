import re


def clean_text(text: str) -> str:
    text = text.replace("\r", "\n")
    text = text.replace("\ufeff", "")
    text = re.sub(r"\n+", "\n", text)
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\s*:\s*", ": ", text)
    text = re.sub(r"[\u0000-\u001f]", "", text)
    return text.strip()