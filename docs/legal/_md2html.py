#!/usr/bin/env python3
"""Tiny markdown-to-HTML converter for the legal drafts.
Handles the subset of markdown used in privacy-policy.md and terms-of-service.md:
# ## ### headings, **bold**, *italic*, `code`, paragraphs, blockquotes,
unordered lists (- ), ordered lists (1. ), and pipe tables.
Strips the first '> Drafting note ...' blockquote block automatically.
"""
import html
import re
import sys
from pathlib import Path


def inline(text: str) -> str:
    text = html.escape(text, quote=False)
    text = re.sub(r"`([^`]+)`", r"<code>\1</code>", text)
    text = re.sub(r"\*\*([^*]+)\*\*", r"<strong>\1</strong>", text)
    text = re.sub(r"(?<!\*)\*([^*\n]+)\*(?!\*)", r"<em>\1</em>", text)
    return text


def convert(md: str) -> str:
    lines = md.splitlines()
    out: list[str] = []
    i = 0
    n = len(lines)
    in_blockquote = False
    blockquote_buf: list[str] = []

    def flush_blockquote(skip_drafting_note: bool):
        nonlocal in_blockquote, blockquote_buf
        if not blockquote_buf:
            return
        joined = "\n".join(blockquote_buf).strip()
        if skip_drafting_note and joined.lower().startswith("**drafting note"):
            blockquote_buf = []
            in_blockquote = False
            return
        out.append("<blockquote>")
        out.append("<p>" + inline(joined).replace("\n", "<br>") + "</p>")
        out.append("</blockquote>")
        blockquote_buf = []
        in_blockquote = False

    drafting_seen = False
    while i < n:
        line = lines[i]

        if line.startswith("> "):
            in_blockquote = True
            blockquote_buf.append(line[2:])
            i += 1
            continue
        elif in_blockquote:
            flush_blockquote(skip_drafting_note=not drafting_seen)
            drafting_seen = True

        m = re.match(r"^(#{1,6})\s+(.+)$", line)
        if m:
            level = len(m.group(1))
            out.append(f"<h{level}>{inline(m.group(2).strip())}</h{level}>")
            i += 1
            continue

        if re.match(r"^---+\s*$", line):
            out.append("<hr>")
            i += 1
            continue

        if line.startswith("|") and i + 1 < n and re.match(r"^\|[\s\-:|]+\|\s*$", lines[i + 1]):
            header_cells = [c.strip() for c in line.strip().strip("|").split("|")]
            i += 2
            rows = []
            while i < n and lines[i].startswith("|"):
                row_cells = [c.strip() for c in lines[i].strip().strip("|").split("|")]
                rows.append(row_cells)
                i += 1
            out.append("<table>")
            out.append("<thead><tr>" + "".join(f"<th>{inline(c)}</th>" for c in header_cells) + "</tr></thead>")
            out.append("<tbody>")
            for r in rows:
                out.append("<tr>" + "".join(f"<td>{inline(c)}</td>" for c in r) + "</tr>")
            out.append("</tbody></table>")
            continue

        m_ul = re.match(r"^(\s*)-\s+(.*)$", line)
        m_ol = re.match(r"^(\s*)\d+\.\s+(.*)$", line)
        if m_ul or m_ol:
            tag = "ul" if m_ul else "ol"
            out.append(f"<{tag}>")
            while i < n:
                m2 = re.match(r"^\s*-\s+(.*)$" if m_ul else r"^\s*\d+\.\s+(.*)$", lines[i])
                if not m2:
                    break
                out.append(f"<li>{inline(m2.group(1).strip())}</li>")
                i += 1
            out.append(f"</{tag}>")
            continue

        if line.strip() == "":
            i += 1
            continue

        para = [line]
        i += 1
        while i < n and lines[i].strip() != "" and not lines[i].startswith(("#", "-", ">", "|")) and not re.match(r"^\d+\.\s", lines[i]) and not re.match(r"^---+\s*$", lines[i]):
            para.append(lines[i])
            i += 1
        joined = " ".join(s.strip() for s in para)
        out.append(f"<p>{inline(joined)}</p>")

    if in_blockquote:
        flush_blockquote(skip_drafting_note=not drafting_seen)

    return "\n".join(out) + "\n"


if __name__ == "__main__":
    here = Path(__file__).parent
    for src_name, dst_name in [
        ("privacy-policy.md", "privacy-policy.html"),
        ("terms-of-service.md", "terms-of-service.html"),
    ]:
        md = (here / src_name).read_text(encoding="utf-8")
        html_out = convert(md)
        (here / dst_name).write_text(html_out, encoding="utf-8")
        print(f"wrote {dst_name} ({len(html_out)} chars)")
