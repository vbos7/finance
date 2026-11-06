import { useState, useEffect } from "react";
import { format, parse, isValid } from "date-fns";
import { ptBR } from "date-fns/locale";
import { ChevronDownIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Combobox, ComboboxContent, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList } from "@/components/ui/combobox";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Spinner } from "@/components/ui/spinner";
import CurrencyInput from "@/components/currency-input";

export interface InvestimentoFormData {
    produto: string;
    empresa: string;
    valor: string;
    quantidade: string;
    tipoAtivo: string;
    provento: string;
    frequencia: string;
    data: string;
    dataLimite: string;
}

export interface MetaInvestFormData {
    metaId: number;
    valor: string;
    data: string;
    dataLimite: string;
}

interface MetaOption {
    id: number;
    nome: string;
    valor: number;
    investido: number;
}

interface Props {
    open: boolean;
    onClose: () => void;
    onSubmit: (data: InvestimentoFormData) => void;
    onSubmitMeta?: (data: MetaInvestFormData) => void;
    initialData?: InvestimentoFormData;
    onDelete?: () => void;
    metas?: MetaOption[];
    loading?: boolean;
}

export const TIPO_META = "Meta Financeira";
const TIPOS = ["Ação Brasileira", "Ação Internacional", "Fundo Imobiliário", "ETF", "Renda Fixa", "Criptomoeda", "Outros"];
const FREQUENCIAS = ["Mensal", "Trimestral", "Semestral", "Anual"];
const MESES = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];

const empty: InvestimentoFormData = { produto: "", empresa: "", valor: "", quantidade: "", tipoAtivo: "", provento: "", frequencia: "", data: "", dataLimite: "" };

const inputCls = "w-full h-9 px-3 rounded-md border border-zinc-200 text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950/10 focus:border-zinc-400 bg-white";
const fmt = (v: number): string => v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });

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
    if (idx < 0 || idx > 11) return value;
    return `${MESES[idx]} ${yyyy}`;
};

export default function InvestimentoModal({ open, onClose, onSubmit, onSubmitMeta, initialData, onDelete, metas, loading }: Props) {
    const [form, setForm] = useState<InvestimentoFormData>(empty);
    const sf = (k: keyof InvestimentoFormData, v: string) => setForm(p => ({ ...p, [k]: v }));
    const editing = !!initialData;
    const [popData, setPopData] = useState(false);
    const [tab, setTab] = useState("investimento");
    const [metaId, setMetaId] = useState<number | null>(null);
    const [metaValor, setMetaValor] = useState("");
    const [metaData, setMetaData] = useState("");
    const [popMetaData, setPopMetaData] = useState(false);
    const [recorrente, setRecorrente] = useState(false);
    const [popLimite, setPopLimite] = useState(false);

    useEffect(() => {
        if (open) {
            // Aporte em meta abre na aba de meta; a aba de investimento começa limpa
            // (só data) para não misturar o nome da meta com produto/tipo de ativo
            const isMeta = initialData?.tipoAtivo === TIPO_META;
            setForm(isMeta ? { ...empty, data: initialData.data } : initialData ?? empty);
            setTab(isMeta ? "meta" : "investimento");
            setMetaId(isMeta ? metas?.find(m => m.nome === initialData.produto)?.id ?? null : null);
            setMetaValor(isMeta ? initialData.valor : "");
            setMetaData(initialData?.data ?? "");
            setRecorrente(false);
        }
    }, [open]);

    useEffect(() => {
        if (!recorrente) sf("dataLimite", "");
    }, [recorrente]);

    const handleClose = () => { if (loading) return; setForm(empty); setMetaId(null); setMetaValor(""); setMetaData(""); setRecorrente(false); onClose(); };

    const handleSubmit = () => {
        if (!form.produto || !form.valor || !form.quantidade || !form.data) return;
        onSubmit(form);
    };

    const handleSubmitMeta = () => {
        if (!metaId || !metaValor || !metaData || !onSubmitMeta) return;
        onSubmitMeta({ metaId, valor: metaValor, data: metaData, dataLimite: recorrente ? form.dataLimite : "" });
    };

    const selectedMeta = metas?.find(m => m.id === metaId);
    const metaNames = metas?.map(m => m.nome) ?? [];

    const recorrenciaUI = (
        <>
            <label className="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" checked={recorrente} onChange={e => setRecorrente(e.target.checked)} className="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-950/10" />
                <span className="text-sm text-zinc-600">Recorrente (repetir mensalmente)</span>
            </label>
            {recorrente && (
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
                                defaultMonth={balancoToDate(form.dataLimite)}
                                locale={ptBR}
                                captionLayout="dropdown"
                            />
                        </PopoverContent>
                    </Popover>
                </div>
            )}
        </>
    );

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center" style={{ animation: "fi .15s ease" }}>
            <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={handleClose} />
            <div className="relative bg-white rounded-xl shadow-2xl border border-zinc-200 w-full max-w-lg mx-4" style={{ animation: "si .2s ease" }}>
                <div className="flex items-center justify-between px-6 py-4 border-b border-zinc-100">
                    <h3 className="text-base font-semibold text-zinc-900">{editing ? "Editar Investimento" : "Novo Investimento"}</h3>
                    <button onClick={handleClose} disabled={loading} className="p-1.5 rounded-md hover:bg-zinc-100 text-zinc-400 hover:text-zinc-600 transition-colors disabled:opacity-50">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                    </button>
                </div>

                <Tabs value={tab} onValueChange={setTab} className="w-full">
                    <div className="px-6 pt-4">
                        <TabsList className="w-full">
                            <TabsTrigger value="investimento" className="flex-1">Investimento</TabsTrigger>
                            <TabsTrigger value="meta" className="flex-1">Meta Financeira</TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="investimento" className="mt-0">
                        <form onSubmit={e => { e.preventDefault(); handleSubmit(); }} className="px-6 py-5 space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <label className="block text-sm font-medium text-zinc-700">Produto (Ticker)</label>
                                    <input placeholder="Ex: ITSA4, KNRI11..." value={form.produto} onChange={e => sf("produto", e.target.value)} className={inputCls} />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="block text-sm font-medium text-zinc-700">Empresa</label>
                                    <input placeholder="Ex: Itaúsa..." value={form.empresa} onChange={e => sf("empresa", e.target.value)} className={inputCls} />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <label className="block text-sm font-medium text-zinc-700">Valor Unitário (R$)</label>
                                    <CurrencyInput value={form.valor} onChange={v => sf("valor", v)} className={inputCls} />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="block text-sm font-medium text-zinc-700">Quantidade</label>
                                    <input type="number" step="1" placeholder="0" value={form.quantidade} onChange={e => sf("quantidade", e.target.value)} className={inputCls} />
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <label className="block text-sm font-medium text-zinc-700">Tipo de Ativo</label>
                                <Combobox items={TIPOS} value={form.tipoAtivo || null} onValueChange={val => sf("tipoAtivo", val ?? "")}>
                                    <ComboboxInput placeholder="Selecione..." className="w-full" />
                                    <ComboboxContent>
                                        <ComboboxEmpty>Nenhum item encontrado.</ComboboxEmpty>
                                        <ComboboxList>
                                            {item => <ComboboxItem key={item} value={item}>{item}</ComboboxItem>}
                                        </ComboboxList>
                                    </ComboboxContent>
                                </Combobox>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <label className="block text-sm font-medium text-zinc-700">Provento (R$)</label>
                                    <CurrencyInput value={form.provento} onChange={v => sf("provento", v)} className={inputCls} />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="block text-sm font-medium text-zinc-700">Frequência</label>
                                    <Combobox items={FREQUENCIAS} value={form.frequencia || null} onValueChange={val => sf("frequencia", val ?? "")}>
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
                            <div className="space-y-1.5">
                                <label className="block text-sm font-medium text-zinc-700">Data</label>
                                <Popover open={popData} onOpenChange={setPopData}>
                                    <PopoverTrigger asChild>
                                        <Button variant="outline" data-empty={!form.data} className="w-full h-9 justify-between text-left font-normal text-sm data-[empty=true]:text-muted-foreground">
                                            {form.data || <span>dd/mm/aaaa</span>}
                                            <ChevronDownIcon className="size-4 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-auto p-0" align="start">
                                        <Calendar
                                            mode="single"
                                            selected={toDate(form.data)}
                                            onSelect={d => { sf("data", toStr(d)); setPopData(false); }}
                                            defaultMonth={toDate(form.data)}
                                            locale={ptBR}
                                            captionLayout="dropdown"
                                        />
                                    </PopoverContent>
                                </Popover>
                            </div>
                            {!editing && recorrenciaUI}
                            <div className="pt-1 flex gap-3">
                                {editing && onDelete && (
                                    <button type="button" onClick={onDelete} disabled={loading} className="h-10 px-4 rounded-md border border-red-200 text-red-600 text-sm font-medium hover:bg-red-50 active:bg-red-100 transition-colors disabled:opacity-50">
                                        Excluir
                                    </button>
                                )}
                                <button type="submit" disabled={loading} className="flex-1 h-10 rounded-md bg-zinc-900 text-white text-sm font-medium hover:bg-zinc-800 active:bg-zinc-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
                                    {loading ? <><Spinner className="size-4" />{editing ? " Salvando..." : " Adicionando..."}</> : editing ? "Salvar" : "Adicionar Investimento"}
                                </button>
                            </div>
                        </form>
                    </TabsContent>

                    <TabsContent value="meta" className="mt-0">
                        <form onSubmit={e => { e.preventDefault(); handleSubmitMeta(); }} className="px-6 py-5 space-y-4">
                            <div className="space-y-1.5">
                                <label className="block text-sm font-medium text-zinc-700">Meta Financeira</label>
                                <Combobox items={metaNames} value={selectedMeta?.nome ?? null} onValueChange={val => {
                                    const m = metas?.find(x => x.nome === val);
                                    setMetaId(m?.id ?? null);
                                }}>
                                    <ComboboxInput placeholder="Selecione uma meta..." className="w-full" />
                                    <ComboboxContent>
                                        <ComboboxEmpty>Nenhuma meta encontrada.</ComboboxEmpty>
                                        <ComboboxList>
                                            {item => <ComboboxItem key={item} value={item}>{item}</ComboboxItem>}
                                        </ComboboxList>
                                    </ComboboxContent>
                                </Combobox>
                            </div>
                            {selectedMeta && (
                                <div className="rounded-lg border border-zinc-100 bg-zinc-50/50 p-4 space-y-2">
                                    <div className="flex justify-between text-sm"><span className="text-zinc-400">Valor da Meta</span><span className="font-mono font-semibold text-zinc-900">{fmt(selectedMeta.valor)}</span></div>
                                    <div className="flex justify-between text-sm"><span className="text-zinc-400">Já Investido</span><span className="font-mono text-emerald-600">{fmt(selectedMeta.investido)}</span></div>
                                    <div className="h-px bg-zinc-200" />
                                    <div className="flex justify-between text-sm"><span className="text-zinc-400">Faltante</span><span className="font-mono text-amber-600">{fmt(selectedMeta.valor - selectedMeta.investido)}</span></div>
                                </div>
                            )}
                            <div className="space-y-1.5">
                                <label className="block text-sm font-medium text-zinc-700">Valor a Investir (R$)</label>
                                <CurrencyInput value={metaValor} onChange={v => setMetaValor(v)} className={inputCls} />
                            </div>
                            <div className="space-y-1.5">
                                <label className="block text-sm font-medium text-zinc-700">Data</label>
                                <Popover open={popMetaData} onOpenChange={setPopMetaData}>
                                    <PopoverTrigger asChild>
                                        <Button variant="outline" data-empty={!metaData} className="w-full h-9 justify-between text-left font-normal text-sm data-[empty=true]:text-muted-foreground">
                                            {metaData || <span>dd/mm/aaaa</span>}
                                            <ChevronDownIcon className="size-4 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-auto p-0" align="start">
                                        <Calendar
                                            mode="single"
                                            selected={toDate(metaData)}
                                            onSelect={d => { setMetaData(toStr(d)); setPopMetaData(false); }}
                                            defaultMonth={toDate(metaData)}
                                            locale={ptBR}
                                            captionLayout="dropdown"
                                        />
                                    </PopoverContent>
                                </Popover>
                            </div>
                            {!editing && recorrenciaUI}
                            <div className="pt-1 flex gap-3">
                                {editing && onDelete && (
                                    <button type="button" onClick={onDelete} disabled={loading} className="h-10 px-4 rounded-md border border-red-200 text-red-600 text-sm font-medium hover:bg-red-50 active:bg-red-100 transition-colors disabled:opacity-50">
                                        Excluir
                                    </button>
                                )}
                                <button type="submit" disabled={loading} className="flex-1 h-10 rounded-md bg-zinc-900 text-white text-sm font-medium hover:bg-zinc-800 active:bg-zinc-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
                                    {loading ? <><Spinner className="size-4" />{editing ? " Salvando..." : " Investindo..."}</> : editing ? "Salvar" : "Investir na Meta"}
                                </button>
                            </div>
                        </form>
                    </TabsContent>
                </Tabs>
            </div>
            <style>{`@keyframes fi{from{opacity:0}to{opacity:1}}@keyframes si{from{opacity:0;transform:scale(.96) translateY(8px)}to{opacity:1;transform:scale(1) translateY(0)}}`}</style>
        </div>
    );
}
