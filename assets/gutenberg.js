(() => {
  'use strict';
  const { createElement: el, Fragment } = wp.element;
  const { InspectorControls, InnerBlocks, useBlockProps } = wp.blockEditor;
  const { PanelBody, TextControl, SelectControl, ToggleControl, Notice } = wp.components;
  wp.blocks.registerBlockType('builder-ab/variant', {
    apiVersion: 3, title: 'Variante A/B', description: 'Agrupa el contenido de una variante de un experimento A/B.',
    icon: 'randomize', category: 'design',
    attributes: {
      experimentId: { type: 'number', default: 0 },
      variant: { type: 'string', default: 'a', enum: ['a', 'b'] },
      isGoal: { type: 'boolean', default: false },
    },
    supports: { html: false },
    edit: ({ attributes, setAttributes }) => {
      const props = useBlockProps({ className: 'bat-editor-variant' });
      return el(Fragment, null,
        el(InspectorControls, null,
          el(PanelBody, { title: 'A/B Testing', initialOpen: true },
            el(TextControl, { label: 'ID del experimento', type: 'number', min: 1, value: attributes.experimentId || '',
              help: 'Crea un experimento de elementos en A/B Testing y copia su ID.',
              onChange: (value) => setAttributes({ experimentId: Math.max(0, parseInt(value, 10) || 0) }) }),
            el(SelectControl, { label: 'Variante', value: attributes.variant,
              options: [{ label: 'A', value: 'a' }, { label: 'B', value: 'b' }], onChange: (variant) => setAttributes({ variant }) }),
            el(ToggleControl, { label: 'Contar clics en este contenido', checked: attributes.isGoal,
              help: 'Añade la clase ab-cta. Usa .ab-cta como selector del objetivo.', onChange: (isGoal) => setAttributes({ isGoal }) }),
            el(Notice, { status: 'info', isDismissible: false }, 'Añade otro bloque para la segunda variante con el mismo ID. En el editor se muestran ambas.')
          )
        ),
        el('div', props,
          el('div', { className: 'bat-editor-label', contentEditable: false }, `Variante ${attributes.variant.toUpperCase()} · Experimento ${attributes.experimentId || 'sin asignar'}`),
          el(InnerBlocks)
        )
      );
    },
    save: () => el(InnerBlocks.Content),
  });
})();
