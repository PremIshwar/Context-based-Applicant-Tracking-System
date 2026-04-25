import os
import sys
import json
import re

# ------------------------------------------------------------
# TEXT VALIDATION
# ------------------------------------------------------------

def is_valid_text(text):
    """Check if extracted text is likely valid (not glyph garbage)."""
    if not text or not text.strip():
        return False
    
    letters = sum(c.isalpha() for c in text)
    return letters > len(text) * 0.3  # at least 30% letters


# ------------------------------------------------------------
# CLEANING
# ------------------------------------------------------------

def clean_extracted_text(text):
    text = re.sub(r'\s+', ' ', text)
    # Encode to UTF-8, replacing any unencodable characters
    text = text.encode('utf-8', errors='replace').decode('utf-8')
    text = text.replace('\x96', '-')
    return text.strip()

# ------------------------------------------------------------
# EXTRACTION PIPELINE
# ------------------------------------------------------------

def extract_text_from_pdf(pdf_path):
    text = ""

    # 1. pdfplumber (best first)
    try:
        import pdfplumber
        with pdfplumber.open(pdf_path) as pdf:
            text = "".join(page.extract_text() or "" for page in pdf.pages)

        if is_valid_text(text):
            return clean_extracted_text(text)
    except Exception as e:
        sys.stderr.write(f"pdfplumber error: {e}\n")

    # 2. pdfminer
    try:
        from pdfminer.high_level import extract_text
        text = extract_text(pdf_path)

        if is_valid_text(text):
            return clean_extracted_text(text)
    except Exception as e:
        sys.stderr.write(f"pdfminer error: {e}\n")

    # 3. PyPDF2 (last fallback)
    try:
        import PyPDF2
        with open(pdf_path, 'rb') as f:
            reader = PyPDF2.PdfReader(f)
            text = "".join(page.extract_text() or "" for page in reader.pages)

        if is_valid_text(text):
            return clean_extracted_text(text)
    except Exception as e:
        sys.stderr.write(f"PyPDF2 error: {e}\n")

    # 4. If everything fails, return empty string
    return ""


def main():
    if len(sys.argv) < 2:
        sys.stderr.write("Usage: extractor.py <pdf_path>\n")
        sys.exit(1)
    
    pdf_path = sys.argv[1]
    if not os.path.exists(pdf_path):
        sys.stderr.write(f"File not found: {pdf_path}\n")
        print("")
        return
    
    text = extract_text_from_pdf(pdf_path)
    print(text)  # PHP reads this as applicant_text

if __name__ == '__main__':
    main()
