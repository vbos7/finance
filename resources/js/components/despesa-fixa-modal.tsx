import { useState, useEffect } from "react";
import { format, parse, isValid } from "date-fns";
import { ptBR } from "date-fns/locale";
import { ChevronDownIcon, CopyIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Combobox, ComboboxContent, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList } from "@/components/ui/combobox";
import { Spinner } from "@/components/ui/spinner";
import CurrencyInput from "@/components/currency-input";

export interface DespesaFixaFormData {
    descricao: string;
    categoria: string;
    valor: string;
    vencimento: string;
    status: string;
    dataPgto: string;
    forma: string;
    dataLimite: string;
}

interface Props {
    open: boolean;
    onClose: () => void;
    onSubmit: (data: DespesaFixaFormData) => void;
    initialData?: DespesaFixaFormData;
    copyData?: DespesaFixaFormData;
    onDelete?: () => void;
    onCopy?: () => void;
    categorias?: string[];
    formas?: string[];
    loading?: boolean;
}

const CATEGORIAS_DEFAULT = ["Casa", "Assinaturas", "Transporte", "Farmácia e Saúde", "Mercado", "Alimentação", "Entretenimento", "Shopping", "Utilidades", "Outros"];
const FORMAS_DEFAULT = ["Pix", "Boleto", "Dinheiro", "Cartão de Débito", "Cartão de Crédito Itaú", "Cartão de Crédito Nubank", "Cartão de Crédito Nubank PJ"];
const STATUS = ["Pago", "Pendente"];
const MESES = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];

const empty: DespesaFixaFormData = { descricao: "", categoria: "", valor: "", vencimento: "", status: "", dataPgto: "", forma: "", dataLimite: "" };

const inputCls = "w-full h-9 px-3 rounded-md border border-zinc-200 text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950/10 focus:border-zinc-400 bg-white";

const toDate = (s: string): Date | undefined => {
    if (!s) return undefined;
    const d = parse(s, "dd/MM/yyyy", new Date());
    return isValid(d) ? d : undefined;
};
const toStr = (d: Date | undefined): string => d ? format(d, "dd/MM/yyyy") : "";

const balancoToDate = (value: string): Date | undefined => {
    if (!value) return undefined;
    const d = parse(value, "MM/yyyy", new Date());
    return isValid(d) ? d : undefined;
};
const dateToBalanco = (d: Date | undefined): string => {
    if (!d) return "";
    const mm = String(d.getMonth() + 1).padStart(2, "0");
    return `${mm}/${d.getFullYear()}`;
};
const balancoLabel = (value: string): string => {
    if (!value) return "";
    const [mm, yyyy] = value.split("/");
    const idx = parseInt(mm, 10) - 1;
    return idx >= 0 && idx <= 11 ? `${MESES[idx]} ${yyyy}` : value;
};

export default function DespesaFixaModal({ open, onClose, onSubmit, initialData, copyData, onDelete, onCopy, categorias, formas, loading }: Props) {
    const [form, setForm] = useState<DespesaFixaFormData>(empty);
    const sf = (k: keyof DespesaFixaFormData, v: string) => setForm(p => ({ ...p, [k]: v }));
    const editing = !!initialData;
    const [popVenc, setPopVenc] = useState(false);
    const [popPgto, setPopPgto] = useState(false);
    const [popLimite, setPopLimite] = useState(false);
    const [recorrente, setRecorrente] = useState(false);

    useEffect(() => {
        if (open) { setForm(initialData ?? copyData ?? empty); setRecorrente(false); }
    }, [open]);

    useEffect(() => {
        if (!recorrente) sf("dataLimite", "");
    }, [recorrente]);

    const handleClose = () => { if (loading) return; setForm(empty); setRecorrente(false); onClose(); };

    const handleSubmit = () => {
        if (!form.descricao || !form.valor || !form.vencimento) return;
        onSubmit(form);
    };

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center" style={{ animation: "fi .15s ease" }}>
            <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={handleClose} />
            <div className="relative bg-white rounded-xl shadow-2xl border border-zinc-200 w-full max-w-lg mx-4" style={{ animation: "si .2s ease" }}>
                <div className="flex items-center justify-between px-6 py-4 border-b border-zinc-100">
                    <h3 className="text-base font-semibold text-zinc-900">{editing ? "Editar Despesa Fixa" : "Nova Despesa Fixa"}</h3>
                    <button onClick={handleClose} disabled={loading} className="p-1.5 rounded-md hover:bg-zinc-100 text-zinc-400 hover:text-zinc-600 transition-colors disabled:opacity-50">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                    </button>
                </div>
                <form onSubmit={e => { e.preventDefault(); handleSubmit(); }} className="px-6 py-5 space-y-4">
                    <div className="space-y-1.5">
                        <label className="block text-sm font-medium text-zinc-700">Descrição</label>
                        <input placeholder="Ex: Luz, Internet..." value={form.descricao} onChange={e => sf("descricao", e.target.value)} className={inputCls} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-zinc-700">Categoria</label>
                            <Combobox items={categorias ?? CATEGORIAS_DEFAULT} value={form.categoria || null} onValueChange={val => sf("categoria", val ?? "")}>
                                <ComboboxInput placeholder="Selecione..." className="w-full" />
                                <ComboboxContent>
                                    <ComboboxEmpty>Nenhum item encontrado.</ComboboxEmpty>
                                    <ComboboxList>
                                        {item => <ComboboxItem key={item} value={item}>{item}</ComboboxItem>}
                                    </ComboboxList>
                                </ComboboxContent>
                            </Combobox>
                        </div>
                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-zinc-700">Valor (R$)</label>
                            <CurrencyInput value={form.valor} onChange={v => sf("valor", v)} className={inputCls} />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-zinc-700">Vencimento</label>
                            <Popover open={popVenc} onOpenChange={setPopVenc}>
                                <PopoverTrigger asChild>
                                    <Button variant="outline" data-empty={!form.vencimento} className="w-full h-9 justify-between text-left font-normal text-sm data-[empty=true]:text-muted-foreground">
                                        {form.vencimento || <span>dd/mm/aaaa</span>}
                                        <ChevronDownIcon className="size-4 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-auto p-0" align="start">
                                    <Calendar
                                        mode="single"
                                        selected={toDate(form.vencimento)}
                                        onSelect={d => { sf("vencimento", toStr(d)); setPopVenc(false); }}
                                        defaultMonth={toDate(form.vencimento)}
                                        locale={ptBR}
                                        captionLayout="dropdown"
                                    />
                                </PopoverContent>
                            </Popover>
                        </div>
                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-zinc-700">Status</label>
                            <Combobox items={STATUS} value={form.status || null} onValueChange={val => sf("status", val ?? "")}>
                                <ComboboxInput placeholder="Selecione..." className="w-full" />
                                <ComboboxContent>
                                    <ComboboxEmpty>Nenhum item encontrado.</ComboboxEmpty>
                                    <ComboboxList>
                                        {item => <ComboboxItem key={item} value={item}>{item}</ComboboxItem>}
                                    </ComboboxList>
                                </ComboboxContent>
                            </Combobox>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-zinc-700">Data Pagamento</label>
                            <Popover open={popPgto} onOpenChange={setPopPgto}>
                                <PopoverTrigger asChild>
                                    <Button variant="outline" disabled={form.status !== "Pago"} data-empty={!form.dataPgto} className="w-full h-9 justify-between text-left font-normal text-sm data-[empty=true]:text-muted-foreground">
                                        {form.dataPgto || <span>dd/mm/aaaa</span>}
                                        <ChevronDownIcon className="size-4 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-auto p-0" align="start">
                                    <Calendar
                                        mode="single"
                                        selected={toDate(form.dataPgto)}
                                        onSelect={d => { sf("dataPgto", toStr(d)); setPopPgto(false); }}
                                        defaultMonth={toDate(form.dataPgto)}
                                        locale={ptBR}
                                        captionLayout="dropdown"
                                    />
                                </PopoverContent>
                            </Popover>
                        </div>
                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-zinc-700">Forma de Pagamento</label>
                            <Combobox items={formas ?? FORMAS_DEFAULT} value={form.forma || null} onValueChange={val => sf("forma", val ?? "")}>
                                <ComboboxInput placeholder="Selecione..." className="w-full" />
                                <ComboboxContent>
                                    <ComboboxEmpty>Nenhum item encontrado.</ComboboxEmpty>
                                    <ComboboxList>
                                        {item => <ComboboxItem key={item} value={item}>{item}</ComboboxItem>}
                                    </ComboboxList>
                                </ComboboxContent>
                            </Combobox>
                        </div>
                    </div>
                    {!editing && recorrente && (
                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-zinc-700">Repetir até</label>
                            <Popover open={popLimite} onOpenChange={setPopLimite}>
                                <PopoverTrigger asChild>
                                    <Button variant="outline" data-empty={!form.dataLimite} className="w-full h-9 justify-between text-left font-normal text-sm data-[empty=true]:text-muted-foreground">
                                        {form.dataLimite ? balancoLabel(form.dataLimite) : <span>Selecione o mês...</span>}
                                        <ChevronDownIcon className="size-4 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-auto p-0" align="start">
                                    <Calendar
                                        mode="single"
                                        selected={balancoToDate(form.dataLimite)}
                                        onSelect={d => { sf("dataLimite", dateToBalanco(d)); setPopLimite(false); }}
                                        defaultMonth={balancoToDate(form.dataLimite) ?? toDate(form.vencimento)}
                                        locale={ptBR}
                                        captionLayout="dropdown"
                                    />
                                </PopoverContent>
                            </Popover>
                        </div>
                    )}
                    {!editing && (
                        <label className="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" checked={recorrente} onChange={e => setRecorrente(e.target.checked)} className="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-950/10" />
                            <span className="text-sm text-zinc-600">Recorrente (repetir mensalmente)</span>
                        </label>
                    )}
                    <div className="pt-1 flex gap-3">
                        {editing && onDelete && (
                            <button type="button" onClick={onDelete} disabled={loading} className="h-10 px-4 rounded-md border border-red-200 text-red-600 text-sm font-medium hover:bg-red-50 active:bg-red-100 transition-colors disabled:opacity-50">
                                Excluir
                            </button>
                        )}
                        {editing && onCopy && (
                            <button type="button" onClick={onCopy} disabled={loading} className="h-10 px-4 rounded-md border border-zinc-200 text-zinc-700 text-sm font-medium hover:bg-zinc-50 active:bg-zinc-100 transition-colors disabled:opacity-50 inline-flex items-center gap-1.5">
                                <CopyIcon className="size-4" /> Copiar
                            </button>
                        )}
                        <button type="submit" disabled={loading} className="flex-1 h-10 rounded-md bg-zinc-900 text-white text-sm font-medium hover:bg-zinc-800 active:bg-zinc-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
                            {loading ? <><Spinner className="size-4" />{editing ? " Salvando..." : " Adicionando..."}</> : editing ? "Salvar" : "Adicionar Despesa Fixa"}
                        </button>
                    </div>
                </form>
            </div>
            <style>{`@keyframes fi{from{opacity:0}to{opacity:1}}@keyframes si{from{opacity:0;transform:scale(.96) translateY(8px)}to{opacity:1;transform:scale(1) translateY(0)}}`}</style>
        </div>
    );
}
