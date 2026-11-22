import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';

/**
 * Converts every `rem` value in the generated CSS to `px` (1rem = 16px):
 * Tailwind's p-4 -> padding: 16px, text-sm -> font-size: 14px, max-w-md -> max-width: 448px, and so on.
 * It runs after Tailwind, so it also covers values coming from Tailwind itself, not only from our own code.
 */
const REM = /(-?\d*\.?\d+)rem\b/g;
export const remToPx = (value) =>
  value.replace(REM, (_, n) => `${Number((parseFloat(n) * 16).toFixed(4))}px`);

const remToPxPlugin = () => ({
  postcssPlugin: 'rem-to-px',
  OnceExit(root) {
    root.walkDecls((decl) => {
      if (decl.value.includes('rem')) decl.value = remToPx(decl.value);
    });
    root.walkAtRules((rule) => {
      if (rule.params && rule.params.includes('rem')) rule.params = remToPx(rule.params);
    });
  },
});
remToPxPlugin.postcss = true;

export default {
  plugins: [tailwindcss, autoprefixer, remToPxPlugin()],
};
