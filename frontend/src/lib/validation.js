// Client-side checks. They mirror the server rules (RegisterRequest + StrongPassword),
// so users get instant feedback; the server still validates everything again.

const PRINTABLE_ASCII = /^[\x21-\x7E]+$/;               // Latin letters, digits, punctuation; no spaces, no Cyrillic
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const SPECIAL = /[!-\/:-@\[-`{-~]/;                      // ! " # $ % & ' ( ) * + , - . / : ; < = > ? @ [ \ ] ^ _ ` { | } ~

export const PASSWORD_HINT =
  'Не менее 8 символов: латиница, хотя бы одна заглавная буква, цифра и спецсимвол (! @ # $ % & *). Кириллица и пробелы запрещены.';

/** Returns an error message, or '' if the e-mail is acceptable. */
export function validateEmail(value) {
  const v = value.trim();
  if (!v) return 'Введите адрес электронной почты.';
  if (!PRINTABLE_ASCII.test(v)) return 'Email может содержать только латинские буквы, цифры и символы ASCII - кириллица запрещена.';
  if (!EMAIL_RE.test(v)) return 'Введите корректный адрес электронной почты.';
  return '';
}

/** Password policy for NEW passwords (registration, password change). Returns a message or ''. */
export function validateNewPassword(p) {
  if (!p) return 'Введите пароль.';
  if (p.length < 8) return 'Пароль должен содержать не менее 8 символов.';
  if (!PRINTABLE_ASCII.test(p)) return 'Пароль может содержать только латинские буквы, цифры и спецсимволы (без пробелов и кириллицы).';

  const missing = [];
  if (!/[A-Z]/.test(p)) missing.push('заглавной латинской буквы (A–Z)');
  if (!/[0-9]/.test(p)) missing.push('цифры');
  if (!SPECIAL.test(p)) missing.push('спецсимвола (например ! @ # $ % & *)');
  return missing.length ? `В пароле не хватает: ${missing.join(', ')}.` : '';
}
