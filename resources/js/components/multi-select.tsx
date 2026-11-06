import { ChevronDownIcon } from "lucide-react";
import { useState } from "react";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";

interface Props {
    options: string[];
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
    emptyText?: string;
    className?: string;
}

/**
 * Select de múltipla escolha: cada opção tem uma caixa de seleção e o popover
 * continua aberto enquanto o usuário marca/desmarca. Lista vazia = "Todos".
 */
export default function MultiSelect({ options, value, onChange, placeholder = "Todos", emptyText = "Nenhuma opção.", className }: Props) {
    const [open, setOpen] = useState(false);
    const selected = new Set(value);

    const toggle = (opt: string) => {
        const next = new Set(selected);
        if (next.has(opt)) next.delete(opt);
        else next.add(opt);
        // Mantém a ordem das opções para o rótulo do gatilho ficar estável
        onChange(options.filter(o => next.has(o)));
    };

    const allSelected = options.length > 0 && options.every(o => selected.has(o));
    const label = value.length === 0 ? placeholder : value.length <= 2 ? value.join(", ") : `${value.length} selecionados`;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    role="combobox"
                    aria-expanded={open}
                    className={cn(
                        "border-input flex h-8 w-full items-center justify-between gap-2 rounded-md border bg-transparent px-2.5 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50",
                        value.length === 0 ? "text-muted-foreground" : "text-zinc-700",
                        className,
                    )}
                >
                    <span className="truncate">{label}</span>
                    <ChevronDownIcon className="size-4 shrink-0 opacity-50" />
                </button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-(--radix-popover-trigger-width) min-w-48 p-1">
                {options.length === 0 ? (
                    <p className="text-muted-foreground py-2 text-center text-sm">{emptyText}</p>
                ) : (
                    <div className="max-h-64 overflow-x-hidden overflow-y-auto">
                        <label className="hover:bg-accent flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm font-medium select-none">
                            <Checkbox checked={allSelected} onCheckedChange={() => onChange(allSelected ? [] : [...options])} />
                            Selecionar todos
                        </label>
                        <div className="bg-border -mx-1 my-1 h-px" />
                        {options.map(opt => (
                            <label key={opt} className="hover:bg-accent flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm select-none">
                                <Checkbox checked={selected.has(opt)} onCheckedChange={() => toggle(opt)} />
                                <span className="truncate">{opt}</span>
                            </label>
                        ))}
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}
