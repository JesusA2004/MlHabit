import {
    AtSign,
    Box,
    Clapperboard,
    Columns3,
    FileText,
    GitCommitVertical,
    Heading,
    Image,
    Images,
    LayoutGrid,
    Megaphone,
    MessageCircleQuestion,
    Minus,
    MousePointerClick,
    MoveVertical,
    Newspaper,
    Pilcrow,
    Quote,
    SeparatorHorizontal,
    Share2,
    Sparkles,
} from '@lucide/vue';
import type { Component } from 'vue';
import { nodeDef } from '@/studio/schema';

/** Iconos declarados en schema.json (importación explícita, sin cargar todo Lucide). */
const ICONS: Record<string, Component> = {
    AtSign,
    Clapperboard,
    Columns3,
    FileText,
    GitCommitVertical,
    Heading,
    Image,
    Images,
    LayoutGrid,
    Megaphone,
    MessageCircleQuestion,
    Minus,
    MousePointerClick,
    MoveVertical,
    Newspaper,
    Pilcrow,
    Quote,
    SeparatorHorizontal,
    Share2,
    Sparkles,
};

export function iconFor(type: string): Component {
    return ICONS[nodeDef(type)?.icon ?? ''] ?? Box;
}
