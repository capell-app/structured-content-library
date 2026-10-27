// Filament accepts one extension per URL. Fragments select schema variants from
// the same registered module, without duplicate nodes or extra asset registrations.
export default function () {
    const factories = {
        '#bulletList': portableBulletList,
        '#code': portableCode,
        '#nestedInline': portableNestedInline,
        '#emptyInline': portableEmptyInline,
        '#codeBlock': portableCodeBlock,
        '#link': portableLink,
        '#orderedList': portableOrderedList,
    }
    return (factories[new URL(import.meta.url).hash] ?? portableSemantics)()
}

// Use Filament's TipTap instance so the extension shares its editor schema.
export function portableSemantics() {
    return window.FilamentRichEditor.tiptap.core.Extension.create({
        // Filament replaces extensions by name. Replace its presentation-only
        // TextAlign extension as well as adding the portable semantic attributes.
        name: 'textAlign',
        addGlobalAttributes() {
            return [
                {
                    types: ['paragraph', 'heading'],
                    attributes: {
                        // Keep neutral alignment from older JSON documents readable,
                        // without synthesising CSS forbidden by the HTML validator.
                        textAlign: {
                            default: null,
                            rendered: false,
                            parseHTML: () => null,
                        },
                    },
                },
                {
                    types: [
                        'paragraph',
                        'heading',
                        'blockquote',
                        'bulletList',
                        'orderedList',
                        'listItem',
                        'codeBlock',
                        'hardBreak',
                        'emptyInline',
                        'nestedInline',
                        'bold',
                        'italic',
                        'code',
                        'link',
                    ],
                    attributes: {
                        ...Object.fromEntries(
                            ['dir', 'lang'].map((name) => [
                                name,
                                {
                                    default: null,
                                    parseHTML: (element) =>
                                        name === 'dir' &&
                                        !['ltr', 'rtl', 'auto'].includes(
                                            (
                                                element.getAttribute(name) ?? ''
                                            ).toLowerCase(),
                                        )
                                            ? null
                                            : element.getAttribute(name),
                                    renderHTML: (attributes) =>
                                        attributes[name] === null
                                            ? {}
                                            : { [name]: attributes[name] },
                                },
                            ]),
                        ),
                        portableAttributes: {
                            default: null,
                            parseHTML: (element) => parseAttributes(element),
                            renderHTML: (attributes) =>
                                attributes.portableAttributes ?? {},
                        },
                    },
                },
                {
                    types: ['orderedList'],
                    attributes: {
                        start: {
                            default: null,
                            parseHTML: (element) =>
                                parseInteger(element, 'start'),
                            renderHTML: (attributes) =>
                                attributes.start === null
                                    ? {}
                                    : { start: attributes.start },
                        },
                        reversed: {
                            default: false,
                            parseHTML: (element) =>
                                element.hasAttribute('reversed'),
                            renderHTML: (attributes) =>
                                attributes.reversed ? { reversed: '' } : {},
                        },
                        type: {
                            default: null,
                            parseHTML: (element) =>
                                ['1', 'a', 'A', 'i', 'I'].includes(
                                    element.getAttribute('type'),
                                )
                                    ? element.getAttribute('type')
                                    : null,
                            renderHTML: (attributes) =>
                                attributes.type === null
                                    ? {}
                                    : { type: attributes.type },
                        },
                    },
                },
                {
                    types: ['listItem'],
                    attributes: {
                        value: {
                            default: null,
                            parseHTML: (element) =>
                                parseInteger(element, 'value'),
                            renderHTML: (attributes) =>
                                attributes.value === null
                                    ? {}
                                    : { value: attributes.value },
                        },
                    },
                },
            ]
        },
    })
}

// Preserve the HTML validator's open semantic-attribute contract. Native controls
// own their attributes so changing a link or list cannot resurrect imported values.
export function parseAttributes(element, innerCode = false) {
    const tag = element.tagName.toLowerCase()
    const isNative = (name, value) => {
        switch (name) {
            case 'dir':
                return ['', 'ltr', 'rtl', 'auto'].includes(value.toLowerCase())
            case 'lang':
                return true
            case 'href':
            case 'target':
            case 'rel':
                return tag === 'a'
            case 'start':
                return tag === 'ol' && parseInteger(element, name) !== null
            case 'value':
                return tag === 'li' && parseInteger(element, name) !== null
            case 'type':
                return tag === 'ol' && ['1', 'a', 'A', 'i', 'I'].includes(value)
            case 'reversed':
                return tag === 'ol'
            default:
                return false
        }
    }
    const attributes = Object.fromEntries(
        [...element.attributes]
            .filter(
                ({ name, value }) =>
                    isPortableAttributeName(name) &&
                    !(name === 'dir' && value === '') &&
                    (innerCode || !isNative(name, value)),
            )
            .map(({ name, value }) => [name, value]),
    )
    return Object.keys(attributes).length ? attributes : null
}

function parseInteger(element, name) {
    const raw = element.getAttribute(name)
    const number = Number(raw)
    return Number.isSafeInteger(number) && String(number) === raw
        ? number
        : null
}

export function isPortableAttributeName(name) {
    return !/^(?:class|style|id|data-.*|wire:.*|x-.*|on[a-z]+|[@:].*)$/i.test(
        name,
    )
}

// Replace Filament's stock codeBlock before schema construction, using its TipTap instance.
export function portableCodeBlock() {
    return window.FilamentRichEditor.tiptap.core.Node.create({
        name: 'codeBlock',
        group: 'block',
        content: 'inline*',
        code: true,
        defining: true,
        addAttributes() {
            return {
                language: { default: null, rendered: false },
                codeAttributes: {
                    default: null,
                    rendered: false,
                },
            }
        },
        parseHTML() {
            return [{ tag: 'pre', preserveWhitespace: 'full' }]
        },
        renderHTML({ node, HTMLAttributes }) {
            return node.attrs.codeAttributes === null
                ? ['pre', HTMLAttributes, 0]
                : [
                      'pre',
                      HTMLAttributes,
                      ['code', node.attrs.codeAttributes, 0],
                  ]
        },
        addCommands() {
            return {
                setCodeBlock:
                    (attributes) =>
                    ({ commands }) =>
                        commands.setNode(this.name, attributes),
                toggleCodeBlock:
                    (attributes) =>
                    ({ commands }) =>
                        commands.toggleNode(this.name, 'paragraph', attributes),
            }
        },
        addKeyboardShortcuts() {
            return {
                'Mod-Alt-c': () => this.editor.commands.toggleCodeBlock(),
                Tab: () =>
                    this.editor.isActive(this.name) &&
                    this.editor.commands.insertContent('\t'),
                'Mod-Enter': () => this.editor.commands.exitCode(),
                Backspace: () => {
                    const { empty, $anchor } = this.editor.state.selection
                    if (!empty || $anchor.parent.type.name !== this.name)
                        return false
                    if ($anchor.parentOffset === 0)
                        return this.editor.commands.clearNodes()
                    return false
                },
                Enter: () => {
                    const { $from, empty } = this.editor.state.selection
                    if (!empty || $from.parent.type.name !== this.name)
                        return false
                    if (
                        $from.parentOffset !== $from.parent.nodeSize - 2 ||
                        !$from.parent.textContent.endsWith('\n\n')
                    )
                        return false
                    return this.editor
                        .chain()
                        .command(({ tr }) => {
                            tr.delete($from.pos - 2, $from.pos)
                            return true
                        })
                        .exitCode()
                        .run()
                },
                ArrowDown: () => {
                    const { $from, empty } = this.editor.state.selection
                    if (
                        !empty ||
                        $from.parent.type.name !== this.name ||
                        $from.parentOffset !== $from.parent.nodeSize - 2
                    )
                        return false
                    if (this.editor.state.doc.nodeSize - 2 !== $from.after())
                        return false
                    return this.editor.commands.exitCode()
                },
            }
        },
    })
}

// Preserve metadata-only anchors alongside normal links in Filament's schema.
export function portableLink() {
    return window.FilamentRichEditor.tiptap.core.Mark.create({
        name: 'link',
        priority: 1000,
        inclusive: false,
        keepOnSplit: false,
        addAttributes() {
            return {
                href: {
                    default: null,
                    parseHTML: (element) =>
                        sanitiseHref(element.getAttribute('href')),
                },
                target: { default: null },
                rel: { default: null },
                class: { default: null },
            }
        },
        parseHTML() {
            return [{ tag: 'a' }]
        },
        renderHTML({ HTMLAttributes }) {
            return [
                'a',
                { ...HTMLAttributes, href: sanitiseHref(HTMLAttributes.href) },
                0,
            ]
        },
        addCommands() {
            return {
                setLink:
                    (attributes) =>
                    ({ commands }) =>
                        commands.setMark(this.name, attributes),
                toggleLink:
                    (attributes) =>
                    ({ commands }) =>
                        commands.toggleMark(this.name, attributes, {
                            extendEmptyMarkRange: true,
                        }),
                unsetLink:
                    () =>
                    ({ commands }) =>
                        commands.unsetMark(this.name, {
                            extendEmptyMarkRange: true,
                        }),
            }
        },
    })
}

// Replace the stock parser so accepted raw start values cannot become NaN or be rounded.
export function portableOrderedList() {
    const { Node, wrappingInputRule } = window.FilamentRichEditor.tiptap.core
    return Node.create({
        name: 'orderedList',
        group: 'block list',
        content: 'listItem*',
        parseHTML() {
            return [{ tag: 'ol' }]
        },
        renderHTML({ HTMLAttributes }) {
            return ['ol', HTMLAttributes, 0]
        },
        addCommands() {
            return {
                toggleOrderedList:
                    () =>
                    ({ commands }) =>
                        commands.toggleList(this.name, 'listItem'),
            }
        },
        addKeyboardShortcuts() {
            return {
                'Mod-Shift-7': () => this.editor.commands.toggleOrderedList(),
            }
        },
        addInputRules() {
            return [
                wrappingInputRule({
                    find: /^(\d+)\.\s$/,
                    type: this.type,
                    getAttributes: (match) => ({ start: Number(match[1]) }),
                    joinPredicate: (match, node) =>
                        node.childCount + (node.attrs.start ?? 1) ===
                        Number(match[1]),
                }),
            ]
        },
    })
}

// Match the shared public URL boundary before a link is clickable in the editor.
export function sanitiseHref(value) {
    if (typeof value !== 'string') return null
    const url = value.trim()
    if (
        !url ||
        url.includes('\0') ||
        url.slice(0, 2).replaceAll('\\', '/') === '//'
    )
        return null
    return ['/', '#', 'https://', 'http://', 'mailto:', 'tel:'].some((prefix) =>
        url.toLowerCase().startsWith(prefix),
    )
        ? url
        : null
}

// Empty marks have no document position; retain their semantic element as an atom.
export function portableEmptyInline() {
    return window.FilamentRichEditor.tiptap.core.Node.create(
        emptyInlineConfiguration(),
    )
}

function emptyInlineConfiguration() {
    return {
        name: 'emptyInline',
        priority: 1100,
        inline: true,
        group: 'inline',
        atom: true,
        addAttributes() {
            return {
                tag: {
                    default: 'a',
                    rendered: false,
                    parseHTML: (element) => element.tagName.toLowerCase(),
                },
                href: {
                    default: null,
                    parseHTML: (element) =>
                        sanitiseHref(element.getAttribute('href')),
                },
                target: { default: null },
                rel: { default: null },
            }
        },
        parseHTML() {
            return ['a', 'strong', 'em', 'code'].map((tag) => ({
                tag,
                // Extension order alone cannot put node rules before mark rules.
                priority: 1100,
                getAttrs: (element) => (element.hasChildNodes() ? false : null),
            }))
        },
        renderHTML({ node, HTMLAttributes }) {
            return [
                node.attrs.tag,
                { ...HTMLAttributes, href: sanitiseHref(HTMLAttributes.href) },
            ]
        },
    }
}

// Portable HTML permits code within other formatting; the stock mark excludes it.
export function portableCode() {
    const { Mark, markInputRule, markPasteRule } =
        window.FilamentRichEditor.tiptap.core
    return Mark.create({
        name: 'code',
        excludes: '',
        code: true,
        exitable: true,
        parseHTML() {
            return [{ tag: 'code' }]
        },
        renderHTML({ HTMLAttributes }) {
            return ['code', HTMLAttributes, 0]
        },
        addCommands() {
            return {
                setCode:
                    () =>
                    ({ commands }) =>
                        commands.setMark(this.name),
                toggleCode:
                    () =>
                    ({ commands }) =>
                        commands.toggleMark(this.name),
                unsetCode:
                    () =>
                    ({ commands }) =>
                        commands.unsetMark(this.name),
            }
        },
        addKeyboardShortcuts() {
            return { 'Mod-e': () => this.editor.commands.toggleCode() }
        },
        addInputRules() {
            return [
                markInputRule({
                    find: (text) => {
                        const match = /`([^`]+)`(?!`)$/.exec(text)
                        return !match ||
                            (match.index > 0 && text[match.index - 1] === '`')
                            ? null
                            : {
                                  index: match.index,
                                  text: match[0],
                                  replaceWith: match[1],
                              }
                    },
                    type: this.type,
                }),
            ]
        },
        addPasteRules() {
            return [
                markPasteRule({
                    find: (text) => {
                        const matches = []
                        const pattern = /`([^`]+)`(?!`)/g
                        let match
                        while ((match = pattern.exec(text)) !== null) {
                            if (
                                match.index === 0 ||
                                text[match.index - 1] !== '`'
                            )
                                matches.push({
                                    index: match.index,
                                    text: match[0],
                                    replaceWith: match[1],
                                })
                        }
                        return matches
                    },
                    type: this.type,
                }),
            ]
        },
    })
}

// A mark set cannot hold two marks of the same type with different attributes.
export function portableNestedInline() {
    const base = emptyInlineConfiguration()
    return window.FilamentRichEditor.tiptap.core.Node.create({
        ...base,
        name: 'nestedInline',
        priority: 1090,
        atom: false,
        content: 'inline*',
        parseHTML() {
            return ['strong', 'em', 'code'].map((tag) => ({
                tag,
                priority: 1090,
                getAttrs: (element) =>
                    element.parentElement?.closest(tag) ? null : false,
                // Parse children in a fresh mark context; the node already carries the surrounding marks.
                getContent: (element, schema) =>
                    window.FilamentRichEditor.tiptap.pmModel.DOMParser.fromSchema(
                        schema,
                    ).parseSlice(element, {
                        preserveWhitespace: element.closest('pre')
                            ? 'full'
                            : false,
                    }).content,
            }))
        },
        renderHTML(parameters) {
            return [...base.renderHTML(parameters), 0]
        },
    })
}

// Empty stored lists are valid HTML and must not acquire a visible synthetic item.
export function portableBulletList() {
    const { Node, wrappingInputRule } = window.FilamentRichEditor.tiptap.core
    return Node.create({
        name: 'bulletList',
        group: 'block list',
        content: 'listItem*',
        parseHTML() {
            return [{ tag: 'ul' }]
        },
        renderHTML({ HTMLAttributes }) {
            return ['ul', HTMLAttributes, 0]
        },
        addCommands() {
            return {
                toggleBulletList:
                    () =>
                    ({ commands }) =>
                        commands.toggleList(this.name, 'listItem'),
            }
        },
        addKeyboardShortcuts() {
            return {
                'Mod-Shift-8': () => this.editor.commands.toggleBulletList(),
            }
        },
        addInputRules() {
            return [
                wrappingInputRule({ find: /^\s*([-+*])\s$/, type: this.type }),
            ]
        },
    })
}
