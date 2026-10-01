const ARABIC_INDIC_ZERO = 0x0660 // ٠
const EXTENDED_ARABIC_INDIC_ZERO = 0x06F0 // ۰ (Persian/Urdu)

/**
 * Converts Arabic-Indic and Extended Arabic-Indic digits to Latin digits and maps
 * the Arabic decimal separator (U+066B) to "." and the Arabic thousands separator (U+066C) to ",".
 * Users on Arabic keyboards can type prices naturally; the app always stores and shows Latin digits (D5).
 */
export function normalizeDigits(input: string): string {
  let out = ''
  for (const ch of input) {
    const code = ch.codePointAt(0) ?? 0
    if (code >= ARABIC_INDIC_ZERO && code <= ARABIC_INDIC_ZERO + 9) {
      out += String(code - ARABIC_INDIC_ZERO)
    }
    else if (code >= EXTENDED_ARABIC_INDIC_ZERO && code <= EXTENDED_ARABIC_INDIC_ZERO + 9) {
      out += String(code - EXTENDED_ARABIC_INDIC_ZERO)
    }
    else if (ch === '٫') {
      out += '.'
    }
    else if (ch === '٬' || ch === '،') {
      out += ','
    }
    else {
      out += ch
    }
  }
  return out
}
