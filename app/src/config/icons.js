/**
 * icons.js frontend source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
const stroke = (d, extra = {}) => ({
  tag: 'path',
  attrs: {
    d,
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: '2',
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    ...extra,
  },
});

const fill = (d, extra = {}) => ({
  tag: 'path',
  attrs: {
    d,
    fill: 'currentColor',
    ...extra,
  },
});

const circle = (attrs) => ({
  tag: 'circle',
  attrs: {
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: '2',
    ...attrs,
  },
});

export const ICONS = {
  brand: {
    viewBox: '0 0 703 882.5',
    elements: [
      fill(
        'M908.66,248V666a126.5,126.5,0,0,1-207.21,97.41l-16.7-16.7L434.08,496.07l-62-62a47.19,47.19,0,0,0-72,30.86V843.36a47.52,47.52,0,0,0,69.57,35.22l19.3-19.3,56-56,81.19-81.19,10.44-10.44a47.65,47.65,0,0,1,67.63,65.05l-13,13L428.84,952.12l-9.59,9.59a128,128,0,0,1-213.59-95.18V413.17a124.52,124.52,0,0,1,199.78-82.54l22.13,22.13L674.45,599.64l46.22,46.22,17,17a47.8,47.8,0,0,0,71-31.44V270.19a48.19,48.19,0,0,0-75-40.05L720.43,243.4l-68.09,68.09L575.7,388.13a48.39,48.39,0,0,1-67.43-67.93L680,148.46A136,136,0,0,1,908.66,248Z',
        {
          transform: 'translate(-205.66 -112.03)',
        }
      ),
    ],
  },
  'settings-general': {
    viewBox: '0 0 24 24',
    elements: [stroke('M4 8h16M6 12h12M8 16h8')],
  },
  'settings-phones': {
    viewBox: '0 0 24 24',
    elements: [
      stroke('M7 3h10v18H7z'),
      stroke('M9 18h6', { strokeLinecap: 'round' }),
    ],
  },
  'settings-integrations': {
    viewBox: '0 0 24 24',
    elements: [stroke('M12 3v8m0 2v8M7 7l5 5 5-5')],
  },
  'settings-about': {
    viewBox: '0 0 24 24',
    elements: [
      circle({ cx: '12', cy: '12', r: '9' }),
      stroke('M12 10v6', { strokeLinecap: 'round' }),
      stroke('M12 7h.01', { strokeLinecap: 'round' }),
    ],
  },
  save: {
    viewBox: '0 0 24 24',
    elements: [fill('M5 21h14a2 2 0 0 0 2-2V8a1 1 0 0 0-.29-.71l-4-4A1 1 0 0 0 16 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2zm10-2H9v-5h6zM13 7h-2V5h2zM5 5h2v4h8V5h.59L19 8.41V19h-2v-5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v5H5z')],
  },
  close: {
    viewBox: '0 0 16 16',
    elements: [stroke('M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 1 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 1 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z', { strokeWidth: '1.5' })],
  },
  'toast-success': {
    viewBox: '0 0 24 24',
    elements: [
      circle({ cx: '12', cy: '12', r: '10' }),
      stroke('M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414'),
    ],
  },
  'toast-warning': {
    viewBox: '0 0 24 24',
    elements: [
      fill('M12 2 1.75 20h20.5L12 2zm0 4.65 6.06 10.65H5.94L12 6.65z'),
      fill('M11 9h2v5h-2zm0 6h2v2h-2z'),
    ],
  },
  'toast-error': {
    viewBox: '0 0 24 24',
    elements: [
      circle({ cx: '12', cy: '12', r: '10' }),
      fill('M11 11h2v6h-2zm0-4h2v2h-2z'),
    ],
  },
  'toast-info': {
    viewBox: '0 0 24 24',
    elements: [
      circle({ cx: '12', cy: '12', r: '10' }),
      fill('M11 10h2v7h-2zm0-4h2v2h-2z'),
    ],
  },
  fallback: {
    viewBox: '0 0 24 24',
    elements: [circle({ cx: '12', cy: '12', r: '10' }), stroke('M12 8h.01'), stroke('M12 11v5')],
  },
};
