import os
import tkinter as tk
from tkinter import filedialog, messagebox, ttk


class RenameTool:
    def __init__(self, root):
        self.root = root
        self.root.title("Datei-Umbenennungs Tool")
        self.root.geometry("700x500")

        self.folder_path = tk.StringVar()
        self.search_text = tk.StringVar()
        self.replace_text = tk.StringVar()
        self.include_subfolders = tk.BooleanVar()

        self.create_widgets()

    def create_widgets(self):
        # Ordner Auswahl
        frame_folder = ttk.Frame(self.root)
        frame_folder.pack(fill="x", padx=10, pady=5)

        ttk.Label(frame_folder, text="Ordner:").pack(side="left")
        ttk.Entry(frame_folder, textvariable=self.folder_path, width=60).pack(side="left", padx=5)
        ttk.Button(frame_folder, text="Durchsuchen", command=self.select_folder).pack(side="left")

        # Such- / Ersetzungsfelder
        frame_replace = ttk.Frame(self.root)
        frame_replace.pack(fill="x", padx=10, pady=5)

        ttk.Label(frame_replace, text="Suchen nach:").grid(row=0, column=0, sticky="w")
        ttk.Entry(frame_replace, textvariable=self.search_text, width=30).grid(row=0, column=1, padx=5)

        ttk.Label(frame_replace, text="Ersetzen durch:").grid(row=1, column=0, sticky="w")
        ttk.Entry(frame_replace, textvariable=self.replace_text, width=30).grid(row=1, column=1, padx=5)

        ttk.Checkbutton(
            frame_replace,
            text="Unterordner einbeziehen",
            variable=self.include_subfolders
        ).grid(row=2, column=0, columnspan=2, sticky="w", pady=5)

        # Buttons
        frame_buttons = ttk.Frame(self.root)
        frame_buttons.pack(fill="x", padx=10, pady=5)

        ttk.Button(frame_buttons, text="Vorschau anzeigen", command=self.preview).pack(side="left", padx=5)
        ttk.Button(frame_buttons, text="Umbenennen", command=self.rename_files).pack(side="left", padx=5)

        # Ausgabe
        self.output = tk.Text(self.root, wrap="none")
        self.output.pack(fill="both", expand=True, padx=10, pady=10)

    def select_folder(self):
        folder = filedialog.askdirectory()
        if folder:
            self.folder_path.set(folder)

    def get_files(self):
        folder = self.folder_path.get()
        if not folder:
            messagebox.showerror("Fehler", "Bitte einen Ordner auswählen.")
            return []

        files = []

        if self.include_subfolders.get():
            for root, _, filenames in os.walk(folder):
                for file in filenames:
                    files.append(os.path.join(root, file))
        else:
            for file in os.listdir(folder):
                full_path = os.path.join(folder, file)
                if os.path.isfile(full_path):
                    files.append(full_path)

        return files

    def preview(self):
        self.output.delete("1.0", tk.END)

        search = self.search_text.get()
        replace = self.replace_text.get()

        if not search:
            messagebox.showerror("Fehler", "Suchtext darf nicht leer sein.")
            return

        files = self.get_files()

        count = 0
        for file_path in files:
            directory, filename = os.path.split(file_path)
            if search in filename:
                new_name = filename.replace(search, replace)
                self.output.insert(tk.END, f"{filename}  →  {new_name}\n")
                count += 1

        self.output.insert(tk.END, f"\n{count} Datei(en) würden umbenannt werden.")

    def rename_files(self):
        search = self.search_text.get()
        replace = self.replace_text.get()

        if not search:
            messagebox.showerror("Fehler", "Suchtext darf nicht leer sein.")
            return

        confirm = messagebox.askyesno("Bestätigen", "Dateien wirklich umbenennen?")
        if not confirm:
            return

        files = self.get_files()
        renamed_count = 0

        for file_path in files:
            directory, filename = os.path.split(file_path)
            if search in filename:
                new_name = filename.replace(search, replace)
                new_path = os.path.join(directory, new_name)

                if not os.path.exists(new_path):
                    os.rename(file_path, new_path)
                    renamed_count += 1
                else:
                    self.output.insert(tk.END, f"Übersprungen (existiert): {new_name}\n")

        messagebox.showinfo("Fertig", f"{renamed_count} Datei(en) umbenannt.")
        self.preview()


if __name__ == "__main__":
    root = tk.Tk()
    app = RenameTool(root)
    root.mainloop()